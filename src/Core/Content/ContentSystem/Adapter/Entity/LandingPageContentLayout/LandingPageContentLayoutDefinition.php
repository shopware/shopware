<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity\LandingPageContentLayout;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class LandingPageContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'landing_page_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'landing_page';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LandingPageContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LandingPageContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return [LandingPageRoute::buildName($entityId)];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('landing_page_id', 'landingPageId');
    }
}
