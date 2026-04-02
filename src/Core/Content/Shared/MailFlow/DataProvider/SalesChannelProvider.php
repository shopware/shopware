<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow\DataProvider;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<SalesChannelEntity, SalesChannelCollection>
 */
#[Package('after-sales')]
class SalesChannelProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociations([
            'domains',
            'mailHeaderFooter',
        ]);

        return $criteria;
    }
}
