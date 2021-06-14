<?php


namespace Xycc\Winter\Validator;


use Carbon\Carbon;
use Countable;
use Exception;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use SplFileInfo;
use Xycc\Winter\Contract\{Attributes\Autowired,
    Attributes\Component,
    Attributes\NoProxy,
    Components\AttributeParser,
    Container\ContainerContract
};
use Xycc\Winter\Http\Request\Request;
use Xycc\Winter\Validator\Attributes\{Accepted,
    AfterDate,
    BeforeDate,
    Date,
    Email,
    EndWith,
    GreaterThan,
    In,
    Ip,
    LessThan,
    Max,
    Min,
    NotEmpty,
    NotIn,
    Numeric,
    Present,
    Range,
    Regex,
    Rule,
    Same,
    Size,
    Sometimes,
    StartWith,
    Type,
    Unique,
    ValidateAllData,
    Validation,
    Validator as ValidatorAttr
};
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
            $this->beforeValidation[$rule::class] = AttributeParser::hasAttribute((new ReflectionClass($rule::class))->getAttributes(), ValidateAllData::class);
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

    protected function greaterThan(array $data, ReflectionProperty $field, GreaterThan $greaterThan): bool|string
    {
        if (!isset($data[$field->name]) || !isset($data[$greaterThan->field])) {
            return sprintf('cannot compare %s and %s, one is not in the request', $field->name, $greaterThan->field);
        }

        if ($greaterThan->eq) {
            return $data[$field->name] >= $data[$greaterThan->field] ? true : sprintf('%s is not greater than nor equals %s', $field->name, $greaterThan->field);
        }
        return $data[$field->name] > $data[$greaterThan->field] ? true : sprintf('%s is not greater than %s', $field->name, $greaterThan->field);
    }

    protected function in($value, In $in): bool|string
    {
        return in_array($value, $in->range)
            ? true
            : ($in->errorMsg ?: sprintf('%s is not in %s', $value, implode(', ', $in->range)));
    }

    protected function ip($value, Ip $ip): bool|string
    {
        return filter_var($value, FILTER_VALIDATE_IP, $ip->v6 ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4)
            ? true
            : ($ip->errorMsg ?: sprintf('%s is not a valid ip%s address', $value, $ip->v6 ? 'v6' : 'v4'));
    }

    protected function lessThan(array $data, ReflectionProperty $field, LessThan $lessThan): bool|string
    {
        if (!isset($data[$field->name]) && !isset($data[$lessThan->field])) {
            return sprintf('cannot compare %s and %s, one is not in the request', $field->name, $lessThan->field);
        }

        if ($lessThan->eq) {
            return $data[$field->name] <= $data[$lessThan->field] ? true : sprintf('%s is not less than nor equals %s', $field->name, $lessThan->field);
        }
        return $data[$field->name] < $data[$lessThan->field] ? true : sprintf('%s is not less than %s', $field->name, $lessThan->field);
    }

    protected function max($value, Max $max): bool|string
    {
        return $value <= $max->max ? true : ($max->errorMsg ?: sprintf('%s is greater than %s', $value, $max->max));
    }

    protected function min($value, Min $min): bool|string
    {
        return $value >= $min->min ? true : ($min->errorMsg ?: sprintf('%s is less than %s', $value, $min->min));
    }

    protected function notEmpty(array $data, ReflectionProperty $field, NotEmpty $notEmpty): bool|string
    {
        return isset($data[$field->name]) && (!is_bool($data[$field->name]) && !!$data[$field->name])
            ? true
            : ($notEmpty->errorMsg ?: 'must be non empty');
    }

    protected function notIn($value, NotIn $notIn): bool|string
    {
        return in_array($value, $notIn->range, true)
            ? ($notIn->errorMsg ?: sprintf('%s is in %s', $value, implode(',', $notIn->range)))
            : true;
    }

    protected function numeric($value, Numeric $numeric): bool|string
    {
        return is_numeric($value)
            ? true
            : ($numeric->errorMsg ?: sprintf('%s is not a number', $value));
    }

    protected function present(array $data, ReflectionProperty $field, Present $present): bool|string
    {
        return isset($data[$field->name])
            ? true
            : ($present->errorMsg ?: sprintf('%s is not present', $field->name));
    }

    protected function range($value, Range $range): bool|string
    {
        $start = $range->startClose ? ($value >= $range->start) : ($value > $range->start);
        $end = $range->endClose ? ($value <= $range->end) : ($value < $range->end);
        return $start && $end
            ? true
            : ($range->errorMsg ?: sprintf('%s is not in range %s...%s', $value, $range->start, $range->end));
    }

    protected function regex($value, Regex $regex): bool|string
    {
        $match = !!preg_match($regex->regex, $value);
        if ($regex->not) {
            return $match ? ($regex->errorMsg ?: sprintf('%s is match "%s"', $value, $regex->regex)) : true;
        }
        return $match ? true : ($regex->errorMsg ?: sprintf('%s is not match "%s"', $value, $regex->regex));
    }

    protected function same(array $data, ReflectionProperty $field, Same $same): bool|string
    {
        if (!isset($data[$field->name]) || !isset($data[$same->field])) {
            return sprintf('cannot compare fields: "%s", "%s". One is miss in request', $field->name, $same->field);
        }
        return $data[$field->name] === $data[$same->field]
            ? true
            : sprintf('%s is not same with %s', $field->name, $same->field);
    }

    protected function size($value, Size $size): bool|string
    {
        $ok = false;
        if (is_string($value)) {
            $ok = $size->size === mb_strlen($value);
        } elseif (is_numeric($value)) {
            $ok = $size->size === $value;
        } elseif ($value instanceof Countable) {
            $ok = $size->size === count($value);
        } elseif ($value instanceof SplFileInfo) {
            $ok = $size->size === $value->getSize();
        }

        return $ok ?: ($size->errorMsg ?: sprintf('The size of "%s" is not equals to %s', $value, $size->size));
    }

    protected function sometimes(array $data, ReflectionProperty $field, Sometimes $sometimes): bool|string
    {

    }

    protected function startWith($value, StartWith $startWith): bool|string
    {
        return str_starts_with($value, $startWith->start)
            ? true
            : ($startWith->errorMsg ?: sprintf('%s is not start with %s', $value, $startWith->start));
    }

    protected function type($value, Type $type): bool|string
    {
        if (class_exists($type->type)) {
            $ok = $value instanceof $type->type;
        } else {
            $ok = match ($type->type) {
                'string', 'str' => is_string($value),
                'number', 'numeric', => is_numeric($value),
                'float', 'double' => is_float($value),
                'int', 'integer' => is_int($value),
                'array' => is_array($value),
                'bool', 'boolean' => is_bool($value),
                'null' => is_null($value),
                default => false,
            };
        }

        return $ok ?: ($type->errorMsg ?: 'error type');
    }

    protected function unique($value, Unique $unique): bool|string
    {

    }
}