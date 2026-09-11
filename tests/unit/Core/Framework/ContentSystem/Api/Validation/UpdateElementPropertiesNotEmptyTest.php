<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\Validation\UpdateElementPropertiesNotEmpty;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateElementPropertiesNotEmpty::class)]
class UpdateElementPropertiesNotEmptyTest extends TestCase
{
    #[TestDox('targets the class, so the rule sees both fields of the host DTO')]
    public function testTargetsTheClass(): void
    {
        static::assertSame(Constraint::CLASS_CONSTRAINT, (new UpdateElementPropertiesNotEmpty())->getTargets());
    }
}
