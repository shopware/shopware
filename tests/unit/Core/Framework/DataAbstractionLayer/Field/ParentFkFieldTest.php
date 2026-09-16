<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ParentFkField::class)]
class ParentFkFieldTest extends TestCase
{
    public function testConstructorConfiguresTheParentForeignKey(): void
    {
        $field = new ParentFkField('some-reference-class');

        static::assertSame('parent_id', $field->getStorageName());
        static::assertSame('parentId', $field->getPropertyName());
        static::assertSame('id', $field->getReferenceField());
    }
}
