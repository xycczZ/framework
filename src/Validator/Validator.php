<?php


namespace Xycc\Winter\Validator;


use Carbon\Carbon;
use Exception;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Contract\Components\AttributeParser;
use Xycc\Winter\Contract\Container\ContainerContract;
use Xycc\Winter\Http\Request\Request;
use Xycc\Winter\Validator\Attributes\Accepted;
use Xycc\Winter\Validator\Attributes\AfterDate;
use Xycc\Winter\Validator\Attributes\BeforeDate;
use Xycc\Winter\Validator\Attributes\BeforeValidate;
use Xycc\Winter\Validator\Attributes\Date;
use Xycc\Winter\Validator\Attributes\Email;
use Xycc\Winter\Validator\Attributes\EndWith;
use Xycc\Winter\Validator\Attributes\GreaterThan;
use Xycc\Winter\Validator\Attributes\NotEmpty;
use Xycc\Winter\Validator\Attributes\Rule;
use Xycc\Winter\Validator\Attributes\Validation;
use Xycc\Winter\Validator\Attributes\Validator as ValidatorAttr;
use Xycc\Winter\Validator\Contracts\RuleInterface;
use Xycc\Winter\Validator\Exceptions\NoSuchRuleException;

/**
 * #Rule 验证规则, 标注在字段上
 * #Validate 标注要验证的对象参数上
 * #Validator 标注规则的验证过程， 标注在class上
 * #Validation 标注在类上，要对此类扫描所有的Rule
 */
#[Component, NoProxy]
class Validator
{
    #[Autowired]
    private Request $request;

    /**
     * @var string[][]
     */
    private array $validators;

    /**
     * @var array
     */
    private array $beforeValidation;

    public function __construct(
        private ContainerContract $app
    )
    {
        $this->getAllValidators();
    }

    private function getAllValidators()
    {
        $validators = $this->app->getClassesByAttr(ValidatorAttr::class);
        $result = [];
        foreach ($validators as $validator) {
            $validatorAttr = $validator->getClassAttributes(ValidatorAttr::class)[0]->newInstance();
            if (!is_subclass_of($validator->getClassName(), RuleInterface::class)) {
                throw new RuntimeException('The validation class annotated with #[Validator] must implement "RuleInterface"');
            }
            $scene = $validatorAttr->scene;
            $rule = $validatorAttr->rule;
            if (isset($result[$rule][$scene])) {
                throw new RuntimeException('There can only be one validation rule processing class in the same validation rule and scenario: ' . $rule . ' : ', $scene);
            }
            $result[$rule][$scene] = $validator->getClassName();
        }
        $this->validators = $result;
    }

    /**
     * 验证数据，验证通过就赋值给这个对象
     * 验证数据， 解析对象的所有字段注解 ？？？ 一定要用字段吗
     * 结合laravel的orm， 标在request上还是直接标注在model上？
     *
     * @see Validation
     */
    public function validate(array $data, object $object, string $scene = 'default'): array
    {
        $attrs = AttributeParser::parseClass($object::class);
        if (!isset($attrs[Validation::class])) {
            throw new RuntimeException('Wrong entity. The object to be validated must be annotated with #[Validation]');
        }

        $ref = new ReflectionClass($object);
        $fields = $ref->getProperties();
        $fields = array_filter($fields, fn (ReflectionProperty $prop) => !$prop->isStatic() && AttributeParser::hasAttribute($prop->getAttributes(), Rule::class));

        $fastFail = $attrs[Validation::class]->newInstance()->fastFail;

        $errors = [];

        foreach ($fields as $field) {
            $error = $this->validateField($object, $field, $scene, $data, $fastFail);
            if ($error) {
                if ($fastFail) {
                    return $error;
                }
                $errors[$field->name] = $error;
            }
        }

        return $errors;
    }

    protected function validateField($object, ReflectionProperty $field, string $scene, $data, bool $fastFail): array
    {
        $rules = $field->getAttributes(Rule::class, ReflectionAttribute::IS_INSTANCEOF);

        // filter scenes
        $rules = $this->filterSceneRules($rules, $scene);

        if (count($rules) === 0) {
            return [];
        }

        $errors = [];

        foreach ($rules as $rule) {
            $method = $this->getFilterMethod($rule::class);
            // 查找本类中是否有这个方法，没有这个方法就找Validator验证类， 包含了这个规则的验证类
            if (!method_exists($this, $method) && !isset($this->validators[$rule::class][$scene])) {
                throw new NoSuchRuleException($method);
            }

            if (method_exists($this, $method)) {
                if ($this->isBeforeValidation($rule)) {
                    $ok = $this->{$method}($data, $field, $rule);
                } else {
                    if (!isset($data[$field->name])) {
                        continue;
                    }
                    $ok = $this->{$method}($data[$field->name], $rule);
                }

                if ($ok !== true) {
                    if ($fastFail) {
                        return [$method => $ok];
                    } else {
                        $errors[$method] = $ok;
                    }
                }
            } else {
                $validatorClass = $this->validators[$rule::class][$scene];
                $validator = $this->app->get($validatorClass);
                /**@var RuleInterface $validator */
                if ($this->isBeforeValidation($rule)) {
                    $result = $validator->validate($data, $this->request, $rule);
                } else {
                    if (!isset($data[$field->name])) {
                        continue;
                    }
                    $result = $validator->validate($data[$field->name], $this->request, $rule);
                }
                if ($result !== true) {
                    if ($fastFail) {
                        return [$validator->name() => $validator->message()];
                    } else {
                        $errors[$validator->name()] = $validator->message();
                    }
                }
            }
        }

        if (count($errors) === 0) {
            if (isset($data[$field->name])) {
                $field->setAccessible(true);
                $field->setValue($object, $data[$field->name]);
            }
        }

        return $errors;
    }

    private function isBeforeValidation(Rule $rule): bool
    {
        if (!isset($this->beforeValidation[$rule::class])) {
            $this->beforeValidation[$rule::class] = AttributeParser::hasAttribute((new ReflectionClass($rule::class))->getAttributes(), BeforeValidate::class);
        }
        return $this->beforeValidation[$rule::class];
    }

    /**
     * @param ReflectionAttribute[] $rules
     * @return Rule[]
     */
    protected function filterSceneRules(array $rules, string $scene): array
    {
        return filter_map($rules, function (ReflectionAttribute $rule) use ($scene) {
            $rule = $rule->newInstance();
            if (in_array($scene, $rule->scenes)) {
                return $rule;
            }
            return null;
        });
    }

    protected function getFilterMethod(string $ruleClass): string
    {
        $seg = explode('\\', $ruleClass);
        return lcfirst(end($seg));
    }

    protected function accepted($value, Accepted $accepted): bool|string
    {
        if (is_string($value)) {
            $value = strtolower($value);
        }
        return in_array($value, [true, 'yes', 'on', 1, '1'], true)
            ? true
            : ($accepted->errorMsg ?: 'no accepted');
    }

    protected function afterDate($value, AfterDate $afterDate): bool|string
    {
        $date = Carbon::parseFromLocale($afterDate->dateTime);
        $valueDate = Carbon::parseFromLocale($value);

        if ($afterDate->eq) {
            return $valueDate->greaterThanOrEqualTo($date) ? true : ($afterDate->errorMsg ?: sprintf('%s is not after nor equals %s', $value, $date));
        } else {
            return $valueDate->greaterThan($date) ? true : ($afterDate->errorMsg ?: sprintf('%s is not after %s', $value, $date));
        }
    }

    protected function beforeDate($value, BeforeDate $beforeDate): bool|string
    {
        $date = Carbon::parseFromLocale($beforeDate->dateTime);
        $valueDate = Carbon::parseFromLocale($value);

        if ($beforeDate->eq) {
            return $valueDate->greaterThanOrEqualTo($date) ? true : ($beforeDate->errorMsg ?: sprintf('%s is not before nor equals %s', $value, $date));
        } else {
            return $valueDate->greaterThan($date) ? true : ($beforeDate->errorMsg ?: sprintf('%s is not before %s', $value, $date));
        }
    }

    protected function date($value, Date $date): bool|string
    {
        try {
            Carbon::parseFromLocale($value);
        } catch (Exception $e) {
            return $date->errorMsg ?: $e->getMessage();
        }

        return true;
    }

    protected function email($value, Email $email): bool|string
    {
        return !!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/', $value)
            ? true
            : ($email->errorMsg ?: sprintf('%s is not a valid email address', $value));
    }

    protected function endWith($value, EndWith $endWith): bool|string
    {
        return str_ends_with($value, $endWith->end)
            ? true
            : ($endWith->errorMsg ?: sprintf('%s is not end with %s', $value, $endWith->end));
    }

    protected function notEmpty(array $data, ReflectionProperty $field, NotEmpty $notEmpty): bool|string
    {
        return isset($data[$field->name]) && (!is_bool($data[$field->name]) && !!$data[$field->name])
            ? true
            : ($notEmpty->errorMsg ?: 'must be non empty');
    }

    protected function greaterThan($value, GreaterThan $greaterThan): bool|string
    {
        if ($greaterThan->eq) {
            return $value >= $greaterThan->value ? true : ($greaterThan->errorMsg ?: sprintf('%s is not greater than or equals %s', $value, $greaterThan->value));
        }
        return $value > $greaterThan->value ? true : ($greaterThan->errorMsg ?: sprintf('%s is not greater than %s', $value, $greaterThan->value));
    }
}