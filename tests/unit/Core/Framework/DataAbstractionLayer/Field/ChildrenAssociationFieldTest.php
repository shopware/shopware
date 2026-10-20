<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChildrenAssociationField::class)]
class ChildrenAssociationFieldTest extends TestCase
{
    public function testConstructorConfiguresTheParentIdAssociation(): void
    {
        $field = new ChildrenAssociationField(CategoryDefinition::class);

        static::assertSame('children', $field->getPropertyName());
        static::assertSame(CategoryDefinition::class, $field->getReferenceClass());
        static::assertSame('parent_id', $field->getReferenceField());
        static::assertSame('id', $field->getLocalField());
        static::assertTrue($field->is(CascadeDelete::class));
    }
}
