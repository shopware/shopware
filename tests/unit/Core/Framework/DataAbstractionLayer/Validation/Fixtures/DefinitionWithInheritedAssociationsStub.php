<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation\Fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Test fixture with inherited associations
 * Used to test validateInheritanceColumns() to ensure matching helper columns
 */
#[Package('framework')]
class DefinitionWithInheritedAssociationsStub extends DefinitionStub
{
    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new FkField('parent_id', 'parentId', self::class))->addFlags(new Required())
        );
        $fields->add(
            new FkField('optional_id', 'optionalId', self::class)
        );
        $fields->add(
            (new OneToManyAssociationField('children', self::class, 'parent_id'))->addFlags(new Inherited())
        );
        $fields->add(
            (new ManyToOneAssociationField('parent', 'parent_id', self::class, 'id'))->addFlags(new Inherited(), new Required())
        );
        $fields->add(
            (new ManyToOneAssociationField('optional', 'optional_id', self::class, 'id'))->addFlags(new Inherited())
        );

        return $fields;
    }
}
