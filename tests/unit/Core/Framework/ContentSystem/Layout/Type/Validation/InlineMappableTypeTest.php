<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\InlineMappableType;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineMappableType::class)]
class InlineMappableTypeTest extends TestCase
{
    public function testDeclaresItsClassTargetAndBothValidationMessages(): void
    {
        $constraint = new InlineMappableType();

        static::assertSame(Constraint::CLASS_CONSTRAINT, $constraint->getTargets());
        static::assertSame('inlineMappable is only valid with type "string"', $constraint->message);
        static::assertSame('inlineMappable and mappable are mutually exclusive on one property', $constraint->exclusiveMessage);
    }
}
