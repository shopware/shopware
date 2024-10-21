<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\CachedRuleLoader;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\Category\SalesChannel\CachedNavigationRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Content\Product\Events\ProductChangedEventInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CachedProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\CachedProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\CachedProductReviewRoute;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Sitemap\Event\SitemapGeneratedEvent;
use Shopware\Core\Content\Sitemap\SalesChannel\SitemapRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryStateRoute;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\SalesChannel\CurrencyRoute;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\SalesChannel\LanguageRoute;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopware\Core\System\SalesChannel\Context\CachedBaseContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\SystemConfig\CachedSystemConfigLoader;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxDefinition;

#[Package('core')]
class CacheInvalidationSubscriber
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Connection $connection,
        private readonly bool $fineGrainedCacheSnippet,
        private readonly bool $fineGrainedCacheConfig
    ) {
    }

    public function invalidateInitialStateIdLoader(EntityWrittenContainerEvent $event): void
    {
        if (!$event->getPrimaryKeys(StateMachineDefinition::ENTITY_NAME)) {
            return;
        }

        $this->cacheInvalidator->invalidate([InitialStateIdLoader::CACHE_KEY]);
    }

    public function invalidateSitemap(SitemapGeneratedEvent $event): void
    {
        $this->cacheInvalidator->invalidate([
            SitemapRoute::buildName($event->getSalesChannelContext()->getSalesChannelId()),
        ]);
    }

    public function invalidateConfig(): void
    {
        // invalidates the complete cached config
        $this->cacheInvalidator->invalidate([
            CachedSystemConfigLoader::CACHE_TAG,
        ]);
    }

    public function invalidateConfigKey(SystemConfigChangedHook $event): void
    {
        if (Feature::isActive('cache_rework')) {
            $this->cacheInvalidator->invalidate(['global.system.config', CachedSystemConfigLoader::CACHE_TAG]);

            return;
        }

        $keys = [];
        if ($this->fineGrainedCacheConfig) {
            /** @var list<string> $keys */
            $keys = array_map(
                static fn (string $key) => SystemConfigService::buildName($key),
                $event->getWebhookPayload()['changes']
            );
        } else {
            $keys[] = 'global.system.config';
        }

        // invalidates the complete cached config and routes which access a specific key
        $this->cacheInvalidator->invalidate([
            ...$keys,
            CachedSystemConfigLoader::CACHE_TAG,
        ]);
    }

    public function invalidateSnippets(EntityWrittenContainerEvent $event): void
    {
        // invalidates all http cache items where the snippets used
        $snippets = $event->getEventByEntityName(SnippetDefinition::ENTITY_NAME);

        if (!$snippets) {
            return;
        }

        if (Feature::isActive('cache_rework')) {
            $setIds = $this->getSetIds($snippets->getIds());

            if (empty($setIds)) {
                return;
            }

            $this->cacheInvalidator->invalidate(array_map(Translator::tag(...), $setIds));

            return;
        }

        if (!$this->fineGrainedCacheSnippet) {
            $this->cacheInvalidator->invalidate(['shopware.translator']);

            return;
        }

        $tags = [];
        foreach ($snippets->getPayloads() as $payload) {
            if (isset($payload['translationKey'])) {
                $tags[] = Translator::buildName($payload['translationKey']);
            }
        }
        $this->cacheInvalidator->invalidate($tags);
    }

    public function invalidateShippingMethodRoute(EntityWrittenContainerEvent $event): void
    {
        // checks if a shipping method changed or the assignment between shipping method and sales channel
        $logs = [...$this->getChangedShippingMethods($event), ...$this->getChangedShippingAssignments($event)];

        $this->cacheInvalidator->invalidate($logs);
    }

    public function invalidateRules(): void
    {
        // invalidates the rule loader each time a rule changed or a plugin install state changed
        $this->cacheInvalidator->invalidate([CachedRuleLoader::CACHE_KEY]);
    }

    public function invalidateCmsPageIds(EntityWrittenContainerEvent $event): void
    {
        // invalidates all routes and http cache pages where a cms page was loaded, the id is assigned as tag
        /** @var list<string> $ids */
        $ids = array_map(EntityCacheKeyGenerator::buildCmsTag(...), $event->getPrimaryKeys(CmsPageDefinition::ENTITY_NAME));
        $this->cacheInvalidator->invalidate($ids);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-subscriber - Will be removed, use invalidateProduct instead
     */
    public function invalidateProductIds(ProductChangedEventInterface $event): void
    {
        if (Feature::isActive('cache_rework')) {
            return;
        }

        // invalidates all routes which loads products in nested unknown objects, like cms listing elements or cross selling elements
        $this->cacheInvalidator->invalidate(
            array_map(EntityCacheKeyGenerator::buildProductTag(...), $event->getIds())
        );
    }

    public function invalidateProduct(InvalidateProductCache $event): void
    {
        if (Feature::isActive('cache_rework')) {
            $listing = array_map(ProductListingRoute::buildName(...), $this->getProductCategoryIds($event->getIds()));

            $parents = array_map(ProductDetailRoute::buildName(...), $this->getParentIds($event->getIds()));
        } else {
            $listing = array_map(CachedProductListingRoute::buildName(...), $this->getProductCategoryIds($event->getIds()));

            $parents = array_map(CachedProductDetailRoute::buildName(...), $this->getParentIds($event->getIds()));
        }

        $streams = array_map(EntityCacheKeyGenerator::buildStreamTag(...), $this->getStreamIds($event->getIds()));

        $tags = array_merge($listing, $parents, $streams);

        $this->cacheInvalidator->invalidate($tags, force: $event->force);
    }

    public function invalidateStreamIds(EntityWrittenContainerEvent $event): void
    {
        // invalidates all routes which are loaded based on a stream (e.G. category listing and cross selling)
        /** @var string[] $ids */
        $ids = array_map(EntityCacheKeyGenerator::buildStreamTag(...), $event->getPrimaryKeys(ProductStreamDefinition::ENTITY_NAME));
        $this->cacheInvalidator->invalidate($ids);
    }

    public function invalidateCategoryRouteByCategoryIds(CategoryIndexerEvent $event): void
    {
        // invalidates the category route cache when a category changed
        $this->cacheInvalidator->invalidate(array_map(CategoryRoute::buildName(...), $event->getIds()));
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-subscriber - Will be removed, use invalidateProduct instead
     */
    public function invalidateListingRouteByCategoryIds(CategoryIndexerEvent $event): void
    {
        if (Feature::isActive('cache_rework')) {
            return;
        }

        // invalidates the product listing route each time a category changed
        $this->cacheInvalidator->invalidate(array_map(CachedProductListingRoute::buildName(...), $event->getIds()));
    }

    public function invalidateIndexedLandingPages(LandingPageIndexerEvent $event): void
    {
        // invalidates the landing page route, if the corresponding landing page changed
        /** @var list<string> $ids */
        $ids = array_map(LandingPageRoute::buildName(...), $event->getIds());
        $this->cacheInvalidator->invalidate($ids);
    }

    public function invalidateCurrencyRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the currency route when a currency changed or an assignment between the sales channel and currency changed
        $this->cacheInvalidator->invalidate([
            ...$this->getChangedCurrencyAssignments($event),
            ...$this->getChangedCurrencies($event),
        ]);
    }

    public function invalidateLanguageRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the language route when a language changed or an assignment between the sales channel and language changed
        $this->cacheInvalidator->invalidate([
            ...$this->getChangedLanguageAssignments($event),
            ...$this->getChangedLanguages($event),
        ]);
    }

    public function invalidateCountryRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the country route when a country changed or an assignment between the sales channel and country changed
        $this->cacheInvalidator->invalidate([
            ...$this->getChangedCountryAssignments($event),
            ...$this->getChangedCountries($event),
        ]);
    }

    public function invalidateCountryStateRoute(EntityWrittenContainerEvent $event): void
    {
        $tags = [];
        if (
            $event->getDeletedPrimaryKeys(CountryStateDefinition::ENTITY_NAME)
            || $event->getPrimaryKeysWithPropertyChange(CountryStateDefinition::ENTITY_NAME, ['countryId'])
        ) {
            $tags[] = CountryStateRoute::ALL_TAG;
        }

        if (empty($tags)) {
            // invalidates the country-state route when a state changed or an assignment between the state and country changed
            /** @var string[] $tags */
            $tags = array_map(
                CountryStateRoute::buildName(...),
                $event->getPrimaryKeys(CountryDefinition::ENTITY_NAME)
            );
        }

        $this->cacheInvalidator->invalidate($tags);
    }

    public function invalidateSalutationRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the salutation route when a salutation changed
        $this->cacheInvalidator->invalidate([...$this->getChangedSalutations($event)]);
    }

    public function invalidateNavigationRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the navigation route when a category changed or the entry point configuration of an sales channel changed
        $logs = [...$this->getChangedCategories($event), ...$this->getChangedEntryPoints($event)];

        $this->cacheInvalidator->invalidate($logs);
    }

    public function invalidatePaymentMethodRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the payment method route when a payment method changed or an assignment between the sales channel and payment method changed
        $logs = [...$this->getChangedPaymentMethods($event), ...$this->getChangedPaymentAssignments($event)];

        $this->cacheInvalidator->invalidate($logs);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-subscriber - Will be removed, use invalidateProduct instead
     */
    public function invalidateSearch(): void
    {
        if (Feature::isActive('cache_rework')) {
            return;
        }
        // invalidates the search and suggest route each time a product changed
        $this->cacheInvalidator->invalidate([
            'product-suggest-route',
            'product-search-route',
        ]);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-subscriber - Will be removed, use invalidateProduct instead
     */
    public function invalidateDetailRoute(ProductChangedEventInterface $event): void
    {
        if (Feature::isActive('cache_rework')) {
            return;
        }

        /** @var string[] $parentIds */
        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT(LOWER(HEX(parent_id))) FROM product WHERE id IN (:ids) AND parent_id IS NOT NULL AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($event->getIds()), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        // invalidates the product detail route each time a product changed or if the product is no longer available (because out of stock)
        $this->cacheInvalidator->invalidate(
            array_map(CachedProductDetailRoute::buildName(...), [...$parentIds, ...$event->getIds()])
        );
    }

    public function invalidateMedia(MediaIndexerEvent $event): void
    {
        /** @var array{'product_id':string, 'variant_id':string|null} $productIds */
        $productIds = $this->connection->fetchAllAssociative(
            'SELECT
                    LOWER(HEX(pm.product_id)) as product_id,
                    IF(variant.id IS NULL,  NULL, LOWER(HEX(variant.id))) as variant_id
                    FROM product_media AS pm
                    LEFT JOIN product as variant ON (pm.product_id = variant.parent_id)
                    WHERE media_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($event->getIds())],
            ['ids' => ArrayParameterType::STRING]
        );

        $variantIds = array_filter(array_column($productIds, 'variant_id'));
        $uniqueProductIds = array_unique(array_column($productIds, 'product_id'));
        $productIds = array_merge(
            $uniqueProductIds,
            $variantIds,
        );

        if (Feature::isActive('cache_rework')) {
            $this->cacheInvalidator->invalidate(
                array_map(ProductDetailRoute::buildName(...), $productIds)
            );
        } else {
            $this->cacheInvalidator->invalidate(
                array_map(CachedProductDetailRoute::buildName(...), $productIds)
            );
        }
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-subscriber - Will be removed, use invalidateProduct instead
     */
    public function invalidateProductAssignment(EntityWrittenContainerEvent $event): void
    {
        if (Feature::isActive('cache_rework')) {
            // @deprecated tag:v6.7.0 - remove also event listener
            return;
        }

        // invalidates the product listing route, each time a product - category assignment changed
        $ids = $event->getPrimaryKeys(ProductCategoryDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'categoryId');

        $this->cacheInvalidator->invalidate(array_map(CachedProductListingRoute::buildName(...), $ids));
    }

    public function invalidateContext(EntityWrittenContainerEvent $event): void
    {
        // invalidates the context cache - each time one of the entities which are considered inside the context factory changed
        $ids = $event->getPrimaryKeys(SalesChannelDefinition::ENTITY_NAME);
        $keys = array_map(CachedSalesChannelContextFactory::buildName(...), $ids);
        $keys = array_merge($keys, array_map(CachedBaseContextFactory::buildName(...), $ids));

        if ($event->getEventByEntityName(CurrencyDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(PaymentMethodDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(ShippingMethodDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(TaxDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(CountryDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(CustomerGroupDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(LanguageDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        /** @var string[] $keys */
        $keys = array_filter(array_unique($keys));

        if (empty($keys)) {
            return;
        }

        $this->cacheInvalidator->invalidate($keys);
    }

    public function invalidateManufacturerFilters(EntityWrittenContainerEvent $event): void
    {
        // invalidates the product listing route, each time a manufacturer changed
        $ids = $event->getPrimaryKeys(ProductManufacturerDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
                INNER JOIN product ON product.id = product_category_tree.product_id AND product_category_tree.product_version_id = product.version_id
             WHERE product.product_manufacturer_id IN (:ids)
             AND product.version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->cacheInvalidator->invalidate(
            array_map(CachedProductListingRoute::buildName(...), $ids)
        );
    }

    public function invalidatePropertyFilters(EntityWrittenContainerEvent $event): void
    {
        $this->cacheInvalidator->invalidate([...$this->getChangedPropertyFilterTags($event), ...$this->getDeletedPropertyFilterTags($event)]);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-subscriber - Will be removed, use invalidateProduct instead
     */
    public function invalidateReviewRoute(ProductChangedEventInterface $event): void
    {
        if (Feature::isActive('cache_rework')) {
            // @deprecated tag:v6.7.0 - remove also event listener
            return;
        }

        $this->cacheInvalidator->invalidate(
            array_map(CachedProductReviewRoute::buildName(...), $event->getIds())
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-subscriber - Will be removed, use invalidateProduct instead
     */
    public function invalidateListings(ProductChangedEventInterface $event): void
    {
        if (Feature::isActive('cache_rework')) {
            // @deprecated tag:v6.7.0 - remove also event listener
            return;
        }

        // invalidates product listings which are based on the product category assignment
        $this->cacheInvalidator->invalidate(
            array_map(CachedProductListingRoute::buildName(...), $this->getProductCategoryIds($event->getIds()))
        );
    }

    public function invalidateStreamsBeforeIndexing(EntityWrittenContainerEvent $event): void
    {
        // invalidates all stream based pages and routes before the product indexer changes product_stream_mapping
        $ids = $event->getPrimaryKeys(ProductDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return;
        }

        // invalidates product listings which are based on a product stream
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_stream_id))
             FROM product_stream_mapping
             WHERE product_stream_mapping.product_id IN (:ids)
             AND product_stream_mapping.product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->cacheInvalidator->invalidate(
            array_map(EntityCacheKeyGenerator::buildStreamTag(...), $ids)
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-subscriber - Will be removed, use invalidateProduct instead
     */
    public function invalidateStreamsAfterIndexing(ProductChangedEventInterface $event): void
    {
        if (Feature::isActive('cache_rework')) {
            // @deprecated tag:v6.7.0 - remove also event listener
            return;
        }

        // invalidates all stream based pages and routes after the product indexer changes product_stream_mapping
        $ids = $this->getStreamIds($event->getIds());

        $this->cacheInvalidator->invalidate(
            array_map(EntityCacheKeyGenerator::buildStreamTag(...), $ids)
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-subscriber - Will be removed, use invalidateProduct instead
     */
    public function invalidateCrossSellingRoute(EntityWrittenContainerEvent $event): void
    {
        if (Feature::isActive('cache_rework')) {
            // @deprecated tag:v6.7.0 - remove also event listener
            return;
        }

        // invalidates the product detail route for the changed cross selling definitions
        $ids = $event->getPrimaryKeys(ProductCrossSellingDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_id)) FROM product_cross_selling WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->cacheInvalidator->invalidate(
            array_map(CachedProductCrossSellingRoute::buildName(...), $ids)
        );
    }

    /**
     * @return string[]
     */
    private function getDeletedPropertyFilterTags(EntityWrittenContainerEvent $event): array
    {
        // invalidates the product listing route, each time a property changed
        $ids = $event->getDeletedPrimaryKeys(ProductPropertyDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return [];
        }

        $productIds = array_column($ids, 'productId');

        return array_merge(
            array_map(CachedProductDetailRoute::buildName(...), array_unique($productIds)),
            array_map(CachedProductListingRoute::buildName(...), $this->getProductCategoryIds($productIds))
        );
    }

    /**
     * @return string[]
     */
    private function getChangedPropertyFilterTags(EntityWrittenContainerEvent $event): array
    {
        // invalidates the product listing route and detail rule, each time a property group changed
        $propertyGroupIds = array_unique(array_merge(
            $event->getPrimaryKeysWithPayloadIgnoringFields(PropertyGroupDefinition::ENTITY_NAME, ['id', 'updatedAt']),
            array_column($event->getPrimaryKeysWithPayloadIgnoringFields(PropertyGroupTranslationDefinition::ENTITY_NAME, ['propertyGroupId', 'languageId', 'updatedAt']), 'propertyGroupId')
        ));

        // invalidates the product listing route and detail rule, each time a property option changed
        $propertyOptionIds = array_unique(array_merge(
            $event->getPrimaryKeysWithPayloadIgnoringFields(PropertyGroupOptionDefinition::ENTITY_NAME, ['id', 'updatedAt']),
            array_column($event->getPrimaryKeysWithPayloadIgnoringFields(PropertyGroupOptionTranslationDefinition::ENTITY_NAME, ['propertyGroupOptionId', 'languageId', 'updatedAt']), 'propertyGroupOptionId')
        ));

        if (empty($propertyGroupIds) && empty($propertyOptionIds)) {
            return [];
        }

        $productIds = $this->connection->fetchFirstColumn(
            'SELECT product_property.product_id
             FROM product_property
                LEFT JOIN property_group_option productProperties ON productProperties.id = product_property.property_group_option_id
             WHERE productProperties.property_group_id IN (:ids) OR productProperties.id IN (:optionIds)
             AND product_property.product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($propertyGroupIds), 'optionIds' => Uuid::fromHexToBytesList($propertyOptionIds), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY, 'optionIds' => ArrayParameterType::BINARY]
        );
        $productIds = array_unique([...$productIds, ...$this->connection->fetchFirstColumn(
            'SELECT product_option.product_id
                 FROM product_option
                    LEFT JOIN property_group_option productOptions ON productOptions.id = product_option.property_group_option_id
                 WHERE productOptions.property_group_id IN (:ids) OR productOptions.id IN (:optionIds)
                 AND product_option.product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($propertyGroupIds), 'optionIds' => Uuid::fromHexToBytesList($propertyOptionIds), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY, 'optionIds' => ArrayParameterType::BINARY]
        )]);

        if (empty($productIds)) {
            return [];
        }

        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(parent_id, id)))
            FROM product
            WHERE id in (:productIds) AND version_id = :version',
            ['productIds' => $productIds, 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['productIds' => ArrayParameterType::BINARY]
        );

        $categoryIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id))
            FROM product_category_tree
            WHERE product_id in (:productIds) AND product_version_id = :version',
            ['productIds' => $productIds, 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['productIds' => ArrayParameterType::BINARY]
        );

        return [
            ...array_map(CachedProductDetailRoute::buildName(...), array_filter($parentIds)),
            ...array_map(CachedProductListingRoute::buildName(...), array_filter($categoryIds)),
        ];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getProductCategoryIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
             WHERE product_id IN (:ids)
             AND product_version_id = :version
             AND category_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @return list<string>
     */
    private function getChangedShippingMethods(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(ShippingMethodDefinition::ENTITY_NAME);
        if (empty($ids)) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_shipping_method WHERE shipping_method_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(ShippingMethodDefinition::ENTITY_NAME)) {
            $tags[] = ShippingMethodRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(ShippingMethodRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedShippingAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the shipping assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelShippingMethodDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(ShippingMethodRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedPaymentMethods(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(PaymentMethodDefinition::ENTITY_NAME);
        if (empty($ids)) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_payment_method WHERE payment_method_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(PaymentMethodDefinition::ENTITY_NAME)) {
            $tags[] = PaymentMethodRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(PaymentMethodRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedPaymentAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the language assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelPaymentMethodDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(PaymentMethodRoute::buildName(...), $ids);
    }

    /**
     * @return string[]
     */
    private function getChangedCategories(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeysWithPayload(CategoryDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return [];
        }

        /** @var string[] $ids */
        $ids = array_map(NavigationRoute::buildName(...), $ids);

        if (!Feature::isActive('cache_rework')) {
            $ids[] = CachedNavigationRoute::BASE_NAVIGATION_TAG;
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function getChangedEntryPoints(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeysWithPropertyChange(
            SalesChannelDefinition::ENTITY_NAME,
            ['navigationCategoryId', 'navigationCategoryDepth', 'serviceCategoryId', 'footerCategoryId']
        );

        if (empty($ids)) {
            return [];
        }

        return [NavigationRoute::ALL_TAG];
    }

    /**
     * @return list<string>
     */
    private function getChangedCountries(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(CountryDefinition::ENTITY_NAME);
        if (empty($ids)) {
            return [];
        }

        // Used to detect changes to the country itself and invalidate the route for all sales channels in which the country is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_country WHERE country_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(CountryDefinition::ENTITY_NAME)) {
            $tags[] = CountryRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(CountryRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedCountryAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the country assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelCountryDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(CountryRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedSalutations(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(SalutationDefinition::ENTITY_NAME);
        if (empty($ids)) {
            return [];
        }

        return [SalutationRoute::buildName()];
    }

    /**
     * @return list<string>
     */
    private function getChangedLanguages(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(LanguageDefinition::ENTITY_NAME);
        if (empty($ids)) {
            return [];
        }

        // Used to detect changes to the language itself and invalidate the route for all sales channels in which the language is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_language WHERE language_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(LanguageDefinition::ENTITY_NAME)) {
            $tags[] = LanguageRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(LanguageRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedLanguageAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the language assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelLanguageDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(LanguageRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedCurrencies(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(CurrencyDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return [];
        }

        // Used to detect changes to the currency itself and invalidate the route for all sales channels in which the currency is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_currency WHERE currency_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(CurrencyDefinition::ENTITY_NAME)) {
            $tags[] = CurrencyRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(CurrencyRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedCurrencyAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the currency assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelCurrencyDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(CurrencyRoute::buildName(...), $ids);
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getParentIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(parent_id, id))) as id FROM product WHERE id IN (:ids) AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getStreamIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_stream_id))
             FROM product_stream_mapping
             WHERE product_stream_mapping.product_id IN (:ids)
             AND product_stream_mapping.product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getSetIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(snippet_set_id)) FROM snippet WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
