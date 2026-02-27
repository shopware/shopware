<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class CategorySpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param EntityRepository<CategoryContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly CategoryContentLayoutDefinition $definition,
        private readonly EntityLayoutContextFactory $contextFactory,
    ) {
    }

    public function getDecorated(): AbstractSpecificationSource
    {
        throw new DecorationPatternException(self::class);
    }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return $this->contextFactory->supports($path, $this->definition);
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        return $this->contextFactory->resolveLayoutId($path, $context, $this->repository, $this->definition);
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        return $this->contextFactory->resolveSpecificationData($path, $request, $context, $this->repository, $this->definition);
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
}
