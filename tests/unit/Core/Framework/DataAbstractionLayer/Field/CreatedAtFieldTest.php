<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreatedAtField::class)]
class CreatedAtFieldTest extends TestCase
{
    public function testConstructorConfiguresStorageAndRequiredFlag(): void
    {
        $field = new CreatedAtField();

        static::assertSame('created_at', $field->getStorageName());
        static::assertSame('createdAt', $field->getPropertyName());
        static::assertTrue($field->is(Required::class));
    }
}
