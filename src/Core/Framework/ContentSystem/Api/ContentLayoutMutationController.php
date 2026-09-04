<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\BindElement;
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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * The persisted counterpart to {@see LayoutMutationController}.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ContentLayoutMutationController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PersistedLayoutMutator $mutator,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly StoredElementCodec $elementCodec,
        private readonly DraftLayoutDecoder $decoder,
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingRegistry,
        private readonly BindingApplicator $bindingApplicator,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/insert-element', name: 'api.action.content_system.layout.persisted_insert_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function insert(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutInsertRequest $payload,
        Context $context,
    ): Response {
        $mutation = new InsertElement($this->registry, $payload->type, $this->bindingRegistry, $this->bindingApplicator, bindingSpecificationId: $payload->bindingSpecificationId, parentElementId: $payload->parentElementId, index: $payload->index, slot: $payload->slot);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/remove-element', name: 'api.action.content_system.layout.persisted_remove_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function remove(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutRemoveRequest $payload,
        Context $context,
    ): Response {
        return $this->respond($layoutId, $payload->expectedVersion, new RemoveElement($payload->elementId), $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/move-element', name: 'api.action.content_system.layout.persisted_move_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function move(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutMoveRequest $payload,
        Context $context,
    ): Response {
        $mutation = new MoveElement($payload->elementId, $payload->newParentId, $payload->newSlot, $payload->index);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/replace-element', name: 'api.action.content_system.layout.persisted_replace_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function replace(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutReplaceRequest $payload,
        Context $context,
    ): Response {
        $mutation = new ReplaceElement($this->registry, $payload->elementId, $payload->newType, $this->bindingRegistry, $this->bindingApplicator);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/duplicate-element', name: 'api.action.content_system.layout.persisted_duplicate_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function duplicate(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutDuplicateRequest $payload,
        Context $context,
    ): Response {
        return $this->respond($layoutId, $payload->expectedVersion, new DuplicateElement($payload->elementId, $payload->index), $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/wrap-elements', name: 'api.action.content_system.layout.persisted_wrap_elements', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function wrap(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutWrapElementsRequest $payload,
        Context $context,
    ): Response {
        $mutation = new WrapElements($this->registry, $payload->elementIds, $payload->containerType, $payload->slot);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/unwrap-element', name: 'api.action.content_system.layout.persisted_unwrap_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function unwrap(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutUnwrapRequest $payload,
        Context $context,
    ): Response {
        return $this->respond($layoutId, $payload->expectedVersion, new UnwrapElement($payload->containerElementId), $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/attach-element', name: 'api.action.content_system.layout.persisted_attach_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function attach(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutAttachRequest $payload,
        Context $context,
    ): Response {
        $mutation = new AttachElement($this->registry, $this->decoder->decodeOne($payload->element), $payload->parentElementId, $payload->slot, $payload->index);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/bind-element', name: 'api.action.content_system.layout.persisted_bind_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function bind(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutBindRequest $payload,
        Context $context,
    ): Response {
        $mutation = new BindElement($this->bindingRegistry, $payload->bindingSpecificationId, $payload->elementId, $this->bindingApplicator);

        return $this->respond($layoutId, $payload->expectedVersion, $mutation, $context);
    }

    private function respond(string $layoutId, ?string $expectedVersion, LayoutMutation $mutation, Context $context): JsonResponse
    {
        $result = $this->mutator->mutate($layoutId, $expectedVersion, $mutation, $context);

        return new JsonResponse(MutationResponse::fromResult($result, $this->elementCodec));
    }
}
