<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Framework\Validation\Constraint\UuidValidator;

/**
 * @internal
 */
#[CoversClass(UuidValidator::class)]
class UuidValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionBecauseConstraintHasWrongClass(): void
    {
        $wrongConstraint = new ArrayOfUuid();
        $this->expectExceptionObject(FrameworkException::unexpectedType($wrongConstraint, Uuid::class));
        $validator = new UuidValidator();
        $validator->validate([], $wrongConstraint);
    }
}
