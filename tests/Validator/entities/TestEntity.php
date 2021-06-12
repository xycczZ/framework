<?php


namespace Xycc\Winter\Tests\Validator\entities;

use Xycc\Winter\Validator\Attributes\Accepted;
use Xycc\Winter\Validator\Attributes\AfterDate;
use Xycc\Winter\Validator\Attributes\BeforeDate;
use Xycc\Winter\Validator\Attributes\Email;
use Xycc\Winter\Validator\Attributes\EndWith;
use Xycc\Winter\Validator\Attributes\GreaterThan;
use Xycc\Winter\Validator\Attributes\NotEmpty;
use Xycc\Winter\Validator\Attributes\Validation;

#[Validation]
class TestEntity
{
    #[Accepted(['a'], 'error on ok')]
    public string $ok = 'xx';

    #[NotEmpty]
    #[AfterDate('2020-01-01 00:00:00')]
    public string $date;

    #[BeforeDate('2020-01-01 00:00:00', true, ['default'], 'not before')]
    public string $before;

    #[Email]
    public string $email;

    #[EndWith('xyz')]
    public string $end;

    #[GreaterThan(10)]
    public int $gt;
}