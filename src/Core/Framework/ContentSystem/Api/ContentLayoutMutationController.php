<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Shopware\Core\Framework\ContentSystem\Mutation\PersistedLayoutMutator;
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
 * Exposes the eight persisted layout mutation actions. Each loads the stored content_layout named in the path,
 * builds one {@see LayoutMutation}, and commits it through {@see PersistedLayoutMutator}, returning the
 * re-resolved layout plus diagnostics. The persisted counterpart to {@see LayoutMutationController}, which
 * mutates a stateless draft tree without touching storage.
 *
 * @internal
 *
 * @final
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ContentLayoutMutationController
{
    public function __construct(
        private readonly PersistedLayoutMutator $mutator,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly ContentElementFieldSerializer $elementSerializer,
        private readonly DraftLayoutDecoder $decoder,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/insert-element', name: 'api.action.content_system.layout.persisted_insert_element', methods: [Request::METHOD_POST])]
    public function insert(
        string $layoutId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutInsertRequest $payload,
        Context $context,
    ): Response {
        $mutation = new InsertElement($this->registry, $payload->type, $payload->parentElementId, $payload->slot, $payload->index);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/remove-element', name: 'api.action.content_system.layout.persisted_remove_element', methods: [Request::METHOD_POST])]
    public function remove(
        string $layoutId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutRemoveRequest $payload,
        Context $context,
    ): Response {
        return $this->respond($layoutId, $payload->expectedVersion, new RemoveElement($payload->elementId), $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/move-element', name: 'api.action.content_system.layout.persisted_move_element', methods: [Request::METHOD_POST])]
    public function move(
        string $layoutId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutMoveRequest $payload,
        Context $context,
    ): Response {
        $mutation = new MoveElement($payload->elementId, $payload->newParentId, $payload->newSlot, $payload->index);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/replace-element', name: 'api.action.content_system.layout.persisted_replace_element', methods: [Request::METHOD_POST])]
    public function replace(
        string $layoutId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutReplaceRequest $payload,
        Context $context,
    ): Response {
        $mutation = new ReplaceElement($this->registry, $payload->elementId, $payload->newType);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/duplicate-element', name: 'api.action.content_system.layout.persisted_duplicate_element', methods: [Request::METHOD_POST])]
    public function duplicate(
        string $layoutId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutDuplicateRequest $payload,
        Context $context,
    ): Response {
        return $this->respond($layoutId, $payload->expectedVersion, new DuplicateElement($payload->elementId, $payload->index), $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/wrap-elements', name: 'api.action.content_system.layout.persisted_wrap_elements', methods: [Request::METHOD_POST])]
    public function wrap(
        string $layoutId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutWrapRequest $payload,
        Context $context,
    ): Response {
        $mutation = new WrapElements($this->registry, $payload->elementIds, $payload->containerType, $payload->slot);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/unwrap-element', name: 'api.action.content_system.layout.persisted_unwrap_element', methods: [Request::METHOD_POST])]
    public function unwrap(
        string $layoutId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutUnwrapRequest $payload,
        Context $context,
    ): Response {
        return $this->respond($layoutId, $payload->expectedVersion, new UnwrapElement($payload->containerElementId), $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/attach-element', name: 'api.action.content_system.layout.persisted_attach_element', methods: [Request::METHOD_POST])]
    public function attach(
        string $layoutId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutAttachRequest $payload,
        Context $context,
    ): Response {
        $mutation = new AttachElement($this->decoder->decodeOne($payload->element), $payload->parentElementId, $payload->slot, $payload->index);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    private function respond(string $layoutId, ?string $expectedVersion, LayoutMutation $mutation, Context $context): JsonResponse
    {
        $result = $this->mutator->mutate($layoutId, $expectedVersion, $mutation, $context);

        $normalizer = new LayoutDiagnosticsResultNormalizer();

        return new JsonResponse([
            'layout' => array_map($this->elementSerializer->serializeContentElement(...), $result->layout),
            'resolutions' => (object) $normalizer->normalizeResolutions($result->resolutions),
            'diagnostics' => $normalizer->normalizeReport($result->diagnostics),
            'affectedElementIds' => $result->affectedElementIds,
            'orphaned' => array_map($this->elementSerializer->serializeContentElement(...), $result->orphaned),
            'droppedWiring' => $result->droppedWiring,
            'droppedProperties' => (object) $result->droppedProperties,
        ]);
    }
}
