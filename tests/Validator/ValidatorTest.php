<?php


namespace Xycc\Winter\Tests\Validator;


use Xycc\Winter\Tests\TestCase;
use Xycc\Winter\Tests\Validator\entities\TestEntity;
use Xycc\Winter\Validator\Validator;

class ValidatorTest extends TestCase
{
    private Validator $validator;
    private TestEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entity = new TestEntity();
        $this->validator = $this->app->get(Validator::class);
    }

    public function testAccepted()
    {
        $data = ['ok' => 'yes'];
        $entity = new TestEntity();
        $errors = $this->validator->validate($data, $entity);
        $this->assertCount(0, $errors, 'have errors');
        $this->assertEquals('xx', $entity->ok);

        $errors = $this->validator->validate($data, $entity, 'a');
        $this->assertCount(0, $errors, 'have errors');
        $this->assertEquals('yes', $entity->ok);

        $data2 = ['ok' => false];
        $errors = $this->validator->validate($data2, $entity, 'a');
        $this->assertCount(1, $errors);
        $this->assertEquals('error on ok', $errors['ok']['accepted']);
    }

    public function testAfterDate()
    {
        $data = ['date' => '2100-01-01 00:00:00'];
        $entity = new TestEntity();
        $errors = $this->validator->validate($data, $entity);
        $this->assertCount(0, $errors);
    }

    public function testBeforeDate()
    {
        $data = ['before' => '2020-01-01 00:00:00'];
        $entity = new TestEntity();
        $errors = $this->validator->validate($data, $entity);
        $this->assertCount(1, $errors);
        $this->assertArrayHasKey('date', $errors);
        $this->assertArrayHasKey('notEmpty', $errors['date']);
        $this->assertEquals('xx', $entity->ok);

        $data += ['date' => '2021-01-01 00:00:00'];
        $errors = $this->validator->validate($data, $entity);
        $this->assertCount(0, $errors);
    }

    public function testEmail()
    {
        $data = ['date' => '2021-01-01 00:00:00', 'email' => 'xxx@xxx.com'];
        $errors = $this->validator->validate($data, $this->entity);
        $this->assertCount(0, $errors);

        $data['email'] = '.123@123.com';
        $errors = $this->validator->validate($data, $this->entity);
        $this->assertCount(1, $errors);
    }

    public function testEndWith()
    {
        $data = ['date' => '2021-01-01 00:00:00', 'end' => 'abcxyz'];
        $errors = $this->validator->validate($data, $this->entity);
        $this->assertCount(0, $errors);
        $this->assertEquals('abcxyz', $this->entity->end);
    }

    public function testSize()
    {
        $data = ['date' => '2021-01-01 00:00:00', 'size' => 11, 'gt' => 12];
        $errors = $this->validator->validate($data, $this->entity);
        $this->assertCount(1, $errors);

        $data['size'] = 10;
        $errors = $this->validator->validate($data, $this->entity);
        $this->assertCount(0, $errors);
    }
}