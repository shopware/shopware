<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Called by RenderingSpecificationFactory to assemble a RenderingSpecification
 * from discrete resolution steps.
 */
#[Package('framework')]
abstract class AbstractSpecificationSource
{
    abstract public function supports(string $path, Request $request, SalesChannelContext $context): bool;

    abstract public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string;

    abstract public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData;

    abstract public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string;

    /**
     * @return list<string>
     */
    abstract public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array;

    /**
     * Whether this source can resolve a layout-free specification for the given entity type.
     * Entity sources override this; domain-aware sources (header/footer) keep the default.
     */
    public function supportsEntityType(string $entityType): bool
    {
        return false;
    }

    /**
     * Assembles specification data from an entity id directly, without a layout assignment.
     * Only ever called on sources whose supportsEntityType() returned true.
     */
    public function resolveSpecificationDataForEntity(string $entityId, Request $request, SalesChannelContext $context): SpecificationData
    {
        throw ContentSystemException::unknownEntityType($entityId);
    }
}
