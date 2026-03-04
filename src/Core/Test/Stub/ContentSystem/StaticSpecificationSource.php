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
    ) {
    }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return $this->supports;
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        return $this->layoutId;
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        if ($this->specificationData === null) {
            throw new \LogicException('StaticSpecificationSource::resolveSpecificationData() called but no SpecificationData was configured.');
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
