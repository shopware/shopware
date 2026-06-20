<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes the seven layout mutation actions. Each binds its request DTO, builds one {@see LayoutMutation}, and
 * runs it through {@see MutationPipeline}, returning the re-resolved layout plus diagnostics without persisting.
 *
 * @internal
 *
 * @final
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class LayoutMutationController
{
    public function __construct(
        private readonly MutationPipeline $pipeline,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly SpecificationSourceLocator $sourceLocator,
        private readonly ContentElementFieldSerializer $elementSerializer,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/insert-element', name: 'api.action.content_system.layout.insert_element', methods: [Request::METHOD_POST])]
    public function insert(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        InsertElementRequest $payload,
        Context $context,
    ): Response {
        $mutation = new InsertElement($this->registry, $payload->type, $payload->parentElementId, $payload->slot, $payload->index);

        return $this->respond($mutation, $payload->layout, $payload->entityType, $payload->section, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/remove-element', name: 'api.action.content_system.layout.remove_element', methods: [Request::METHOD_POST])]
    public function remove(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        RemoveElementRequest $payload,
        Context $context,
    ): Response {
        return $this->respond(new RemoveElement($payload->elementId), $payload->layout, $payload->entityType, $payload->section, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/move-element', name: 'api.action.content_system.layout.move_element', methods: [Request::METHOD_POST])]
    public function move(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        MoveElementRequest $payload,
        Context $context,
    ): Response {
        $mutation = new MoveElement($payload->elementId, $payload->newParentId, $payload->newSlot, $payload->index);

        return $this->respond($mutation, $payload->layout, $payload->entityType, $payload->section, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/replace-element', name: 'api.action.content_system.layout.replace_element', methods: [Request::METHOD_POST])]
    public function replace(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ReplaceElementRequest $payload,
        Context $context,
    ): Response {
        $mutation = new ReplaceElement($this->registry, $payload->elementId, $payload->newType);

        return $this->respond($mutation, $payload->layout, $payload->entityType, $payload->section, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/duplicate-element', name: 'api.action.content_system.layout.duplicate_element', methods: [Request::METHOD_POST])]
    public function duplicate(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        DuplicateElementRequest $payload,
        Context $context,
    ): Response {
        return $this->respond(new DuplicateElement($payload->elementId, $payload->index), $payload->layout, $payload->entityType, $payload->section, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/wrap-elements', name: 'api.action.content_system.layout.wrap_elements', methods: [Request::METHOD_POST])]
    public function wrap(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        WrapElementsRequest $payload,
        Context $context,
    ): Response {
        $mutation = new WrapElements($this->registry, $payload->elementIds, $payload->containerType, $payload->slot);

        return $this->respond($mutation, $payload->layout, $payload->entityType, $payload->section, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/unwrap-element', name: 'api.action.content_system.layout.unwrap_element', methods: [Request::METHOD_POST])]
    public function unwrap(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        UnwrapElementRequest $payload,
        Context $context,
    ): Response {
        return $this->respond(new UnwrapElement($payload->containerElementId), $payload->layout, $payload->entityType, $payload->section, $context);
    }

    /**
     * @param array<int|string, mixed> $layout
     */
    private function respond(LayoutMutation $mutation, array $layout, ?string $entityType, ?string $section, Context $context): JsonResponse
    {
        $result = $this->pipeline->run($mutation, $layout, $this->resolveRootContext($entityType, $section, $context), $context);

        $normalizer = new LayoutDiagnosticsResultNormalizer();

        return new JsonResponse([
            'layout' => array_map($this->elementSerializer->serializeContentElement(...), $result->layout),
            'resolutions' => (object) $normalizer->normalizeResolutions($result->resolutions),
            'diagnostics' => $normalizer->normalizeReport($result->diagnostics),
            'affectedElementIds' => $result->affectedElementIds,
            'orphaned' => array_map($this->elementSerializer->serializeContentElement(...), $result->orphaned),
            'droppedWiring' => $result->droppedWiring,
        ]);
    }

    /**
     * @return list<ProvidedContext>|null
     */
    private function resolveRootContext(?string $entityType, ?string $section, Context $context): ?array
    {
        if ($entityType !== null && $entityType !== '') {
            return $this->sourceLocator->resolveByEntityType($entityType)->providedRootContext($context);
        }

        if ($section !== null && $section !== '') {
            $resolved = ContentSection::tryFrom($section) ?? throw ContentSystemException::noSourceForSection($section);

            return $this->sourceLocator->resolveBySection($resolved)->providedRootContext($context);
        }

        return null;
    }
}
