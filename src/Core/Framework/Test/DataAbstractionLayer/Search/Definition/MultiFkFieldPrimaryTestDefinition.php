<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Definition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class MultiFkFieldPrimaryTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'multi_fk_field_primary';
    }

    public function since(): string
    {
        return '6.4.1';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('first_id', 'firstId'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new IdField('second_id', 'secondId'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
        ]);
    }
}
