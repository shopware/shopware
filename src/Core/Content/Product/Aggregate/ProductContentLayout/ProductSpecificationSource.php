<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ProductSpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param EntityRepository<ProductContentLayoutCollection> $repository
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly ProductContentLayoutDefinition $definition,
        private readonly EntityLayoutContextFactory $contextFactory,
        private readonly SalesChannelRepository $productRepository,
    ) {
    }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return $this->contextFactory->supports($path, $this->definition);
    }

    /**
     * A variant without an assignment of its own uses its parent's assignment before the default layout.
     */
    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        $parentId = $this->fetchParentId($path, $context);

        return $this->contextFactory->resolveLayoutId(
            $path,
            $context,
            $this->repository,
            $this->definition,
            $parentId !== null ? [$parentId] : [],
        );
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        return $this->contextFactory->resolveSpecificationData($path, $request, $context, $this->definition);
    }

    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    {
        return $this->contextFactory->resolveTargetElementId($request);
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    {
        return $this->contextFactory->resolveCacheTags($path, $this->definition);
    }

    public function supportsEntityType(string $entityType): bool
    {
        return $this->definition->getContentLayoutEntityType() === $entityType;
    }

    public function resolveSpecificationDataForEntity(string $entityId, Request $request, SalesChannelContext $context): SpecificationData
    {
        return $this->contextFactory->buildSpecificationData($entityId, $request, $context, $this->definition);
    }

    public function providedRootContext(Context $context): array
    {
        return $this->contextFactory->providedRootContext($this->definition);
    }

    public function rootSource(): string
    {
        return $this->definition->getContentLayoutEntityType();
    }

    private function fetchParentId(string $path, SalesChannelContext $context): ?string
    {
        $productId = $this->contextFactory->extractEntityId($path, $this->definition);

        if (!Uuid::isValid($productId)) {
            return null;
        }

        $criteria = (new Criteria([$productId]))->addFields(['id', 'parentId']);
        $parentId = $this->productRepository->search($criteria, $context)->getEntities()->first()?->get('parentId');

        return \is_string($parentId) ? $parentId : null;
    }
}
