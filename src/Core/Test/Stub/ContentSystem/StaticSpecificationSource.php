<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
#[Package('framework')]
class StaticSpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param list<string> $cacheTags
     */
    public function __construct(
        private readonly bool $supports = true,
        private readonly string $layoutId = '',
        private readonly ?SpecificationData $specificationData = null,
        private readonly ?string $targetElementId = null,
        private readonly array $cacheTags = [],
        private readonly ?string $supportedEntityType = null,
        private readonly bool $failOnResolveLayoutId = false,
    ) {
    }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return $this->supports;
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        if ($this->failOnResolveLayoutId) {
            throw new \LogicException('StaticSpecificationSource::resolveLayoutId() must not be called on the layout-free preview path.');
        }

        return $this->layoutId;
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        if ($this->specificationData === null) {
            throw new \LogicException('StaticSpecificationSource::resolveSpecificationData() called but no SpecificationData was configured.');
        }

        return $this->specificationData;
    }

    public function supportsEntityType(string $entityType): bool
    {
        return $entityType === $this->supportedEntityType;
    }

    public function resolveSpecificationDataForEntity(string $entityId, Request $request, SalesChannelContext $context): SpecificationData
    {
        if ($this->specificationData === null) {
            throw new \LogicException('StaticSpecificationSource::resolveSpecificationDataForEntity() called but no SpecificationData was configured.');
        }

        return $this->specificationData;
    }

    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    {
        return $this->targetElementId;
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    {
        return $this->cacheTags;
    }
}
