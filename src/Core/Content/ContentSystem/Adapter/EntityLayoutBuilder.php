<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignableDefinitionInterface;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves content layout assignments and processes placeholders for entity-based rendering.
 *
 * @internal
 */
#[Package('discovery')]
class EntityLayoutBuilder
{
    public function __construct(
        private readonly QueryPlaceholderBinder $parameterProcessor
    ) {
    }

    /**
     * Resolves layout assignment and processes placeholders for entity-based rendering.
     *
     * @template TEntityCollection of \Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutCollection|\Shopware\Core\Content\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutCollection|\Shopware\Core\Content\ContentSystem\Adapter\Entity\LandingPageContentLayout\LandingPageContentLayoutCollection
     *
     * @param EntityRepository<TEntityCollection> $repository
     *
     * @return array{layoutId: string, placeholderValues: PlaceholderValues}
     */
    public function resolve(
        EntityRepository $repository,
        ContentLayoutAssignableDefinitionInterface $definition,
        string $entityId,
        Request $request,
        SalesChannelContext $context
    ): array {
        $entityIdField = $definition->getContentLayoutEntityIdField();
        $entityType = $definition->getContentLayoutEntityType();

        $assignment = EntityLayoutFinder::findLayoutAssignment(
            $entityIdField,
            $entityId,
            $context,
            $repository
        );

        if ($assignment === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                $entityType,
                $entityId,
                $context->getSalesChannel()->getId()
            );
        }

        $layoutId = $assignment->getContentLayoutId();

        // Apply binding to entity ID placeholder if configured
        $entityIdPlaceholder = $entityIdField;
        $bindings = $assignment->getParameterBindings();
        if ($bindings !== null && isset($bindings[$entityIdField])) {
            $entityIdPlaceholder = $bindings[$entityIdField]->placeholder ?? $entityIdField;
        }

        $queryParameters = $request->query->all();
        $processedParameters = $this->parameterProcessor->process(
            $bindings,
            $queryParameters,
            $context
        );

        // Merge entity ID with query parameters
        $placeholderValues = PlaceholderValues::from(array_merge(
            [$entityIdPlaceholder => $entityId],
            $processedParameters
        ));

        return [
            'layoutId' => $layoutId,
            'placeholderValues' => $placeholderValues,
        ];
    }
}
