<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter;

use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Called by RenderingSpecificationFactory to assemble a RenderingSpecification
 * from discrete resolution steps.
 */
#[Package('discovery')]
abstract class AbstractSpecificationSource
{
    abstract public function getDecorated(): AbstractSpecificationSource;

    abstract public function supports(string $path, Request $request, SalesChannelContext $context): bool;

    abstract public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string;

    abstract public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData;

    abstract public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string;

    /**
     * @return list<string>
     */
    abstract public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array;
}
