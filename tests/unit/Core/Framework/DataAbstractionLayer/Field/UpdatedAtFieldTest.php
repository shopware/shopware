<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdatedAtField::class)]
class UpdatedAtFieldTest extends TestCase
{
    public function testConstructorConfiguresTheStorageName(): void
    {
        $field = new UpdatedAtField();

        static::assertSame('updated_at', $field->getStorageName());
        static::assertSame('updatedAt', $field->getPropertyName());
    }
}
