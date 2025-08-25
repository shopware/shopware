<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuidValidator;
use Shopware\Core\Framework\Validation\Constraint\Uuid;

/**
 * @internal
 */
#[CoversClass(ArrayOfUuidValidator::class)]
class ArrayOfUuidValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionBecauseConstraintHasWrongClass(): void
    {
        $wrongConstraint = new Uuid();
        $this->expectExceptionObject(FrameworkException::unexpectedType($wrongConstraint, ArrayOfUuid::class));
        $validator = new ArrayOfUuidValidator();
        $validator->validate([], $wrongConstraint);
    }
}
