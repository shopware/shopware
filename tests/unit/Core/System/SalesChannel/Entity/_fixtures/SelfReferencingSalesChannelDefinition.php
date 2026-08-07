<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Entity\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * An entity that associates itself, so a test can build a criteria of arbitrary depth.
 *
 * @internal
 */
class SelfReferencingSalesChannelDefinition extends EntityDefinition implements SalesChannelDefinitionInterface
{
    public const ENTITY_NAME = 'self_referencing_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new FkField('parent_id', 'parentId', self::class),
            new OneToManyAssociationField('children', self::class, 'parent_id'),
        ]);
    }
}
