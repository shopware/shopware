<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Extension;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;

/**
 * @public This class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Enrich the CMS slots with the loaded data from the search results
 *
 * @description This event allows interception of the enrichment process,
 * during which CMS slots used in a rendered CMS page are populated with data loaded by the respective CMS resolver from the search results.
 *
 * @template TEntityCollection of EntityCollection
 *
 * @extends Extension<CmsSlotCollection>
 */
#[Package('buyers-experience')]
final class CmsSlotsDataEnrichExtension extends Extension
{
    public const NAME = 'cms-slots-data.enrich';

    /**
     * @internal Shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The slot collection which will be enriched with the data of the identifier and criteria results
         */
        public readonly CmsSlotCollection $slots,
        /**
         * @public
         *
         * @description The criteria list which is used for the mapping of the search results
         *
         * @var array<string, CriteriaCollection>
         */
        public readonly array $criteriaList,
        /**
         * @public
         *
         * @description The fetched slot data which was searched by the identifiers
         *
         * @var array<EntitySearchResult<TEntityCollection>>
         */
        public readonly array $identifierResult,
        /**
         * @public
         *
         * @description The fetched slot data which was searched by the criteria list
         *
         * @var array<EntitySearchResult<TEntityCollection>>
         */
        public readonly array $criteriaResult,
        /**
         * @public
         *
         * @description Allows you to access to the current resolver-context
         */
        public readonly ResolverContext $resolverContext,
    ) {
    }
}
