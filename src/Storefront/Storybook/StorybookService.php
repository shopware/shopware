<?php declare(strict_types=1);

namespace Shopware\Storefront\Storybook;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class StorybookService
{
    private const PARAMETER_DENY_LIST = [
        'measureEnabled',
        'backgrounds',
        'outline',
        'viewport',
    ];

    private const ENTITY_PROPERTY_LIST = [
        'product',
        'media',
    ];

    /**
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly AbstractSalesChannelContextFactory $contextFactory,
        private readonly DatabaseSalesChannelThemeLoader $themeLoader,
        private readonly ThemeRuntimeConfigStorage $themeRuntimeConfigStorage,
    ) {
    }

    public function createSalesChannelContext(): SalesChannelContext
    {
        return $this->contextFactory->create('', $this->getFirstAvailableSalesChannelId());
    }

    public function getThemeId(string $salesChannelId): ?string
    {
        $themes = $this->themeLoader->load($salesChannelId);

        if ($themes === []) {
            return null;
        }

        return $this->themeRuntimeConfigStorage->getThemeIdByTechnicalName($themes[0]);
    }

    /**
     * Parses story parameters from the request and resolves any entity sentinels
     * (e.g. "product", "media") to their actual DAL entities.
     *
     * @return array<string, mixed>
     */
    public function resolveComponentProps(Request $request, SalesChannelContext $context): array
    {
        $properties = $this->getPropertiesFromStoryParameters($request);

        return $this->resolveEntityProperties($properties, $context);
    }

    private function getFirstAvailableSalesChannelId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $id = $this->salesChannelRepository
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();

        if ($id === null) {
            throw SalesChannelException::salesChannelNotFound('');
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPropertiesFromStoryParameters(Request $request): array
    {
        $parameters = [];

        foreach ($request->query->all() as $key => $value) {
            // Only allow alphanumeric keys starting with a letter or underscore
            if (!\is_string($key) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                continue;
            }

            if (\in_array($key, self::PARAMETER_DENY_LIST, true)) {
                continue;
            }

            // Store the key as a sentinel so resolveEntityProperties knows to fetch this entity.
            $parameters[$key] = \in_array($key, self::ENTITY_PROPERTY_LIST, true) ? $key : $value;
        }

        return $parameters;
    }

    /**
     * Replaces entity sentinel values with their resolved DAL entities; forwards all other values unchanged.
     *
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function resolveEntityProperties(array $properties, SalesChannelContext $context): array
    {
        $resolved = [];

        foreach ($properties as $key => $value) {
            $resolved[$key] = match ($value) {
                'product' => $this->resolveProductProperty($context),
                'media' => $this->resolveMediaProperty($context),
                default => $value,
            };
        }

        return $resolved;
    }

    private function resolveProductProperty(SalesChannelContext $context): ?SalesChannelProductEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addAssociation('media.media');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('options.group');

        $entity = $this->productRepository->search($criteria, $context)->getEntities()->first();

        return $entity instanceof SalesChannelProductEntity ? $entity : null;
    }

    private function resolveMediaProperty(SalesChannelContext $context): ?MediaEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('mimeType', 'image/jpeg'),
            new EqualsFilter('mimeType', 'image/png'),
        ]));

        return $this->mediaRepository->search($criteria, $context->getContext())->getEntities()->first();
    }
}
