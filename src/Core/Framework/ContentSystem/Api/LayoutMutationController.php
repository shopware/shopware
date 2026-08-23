<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\BindElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
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
 * The stateless draft-tree counterpart to {@see ContentLayoutMutationController}.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class LayoutMutationController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DraftLayoutDecoder $decoder,
        private readonly MutationPipeline $pipeline,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly StoredElementCodec $elementCodec,
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingRegistry,
        private readonly BindingApplicator $bindingApplicator,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/insert-element', name: 'api.action.content_system.layout.insert_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function insert(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        InsertElementRequest $payload,
        Context $context,
    ): Response {
        $mutation = new InsertElement($this->registry, $payload->type, $this->bindingRegistry, $this->bindingApplicator, bindingSpecificationId: $payload->bindingSpecificationId, parentElementId: $payload->parentElementId, index: $payload->index, slot: $payload->slot);

        return $this->respond($mutation, $payload->layout, $payload->rootSource, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/remove-element', name: 'api.action.content_system.layout.remove_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function remove(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        RemoveElementRequest $payload,
        Context $context,
    ): Response {
        return $this->respond(new RemoveElement($payload->elementId), $payload->layout, $payload->rootSource, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/move-element', name: 'api.action.content_system.layout.move_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function move(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        MoveElementRequest $payload,
        Context $context,
    ): Response {
        $mutation = new MoveElement($payload->elementId, $payload->newParentId, $payload->newSlot, $payload->index);

        return $this->respond($mutation, $payload->layout, $payload->rootSource, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/replace-element', name: 'api.action.content_system.layout.replace_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function replace(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ReplaceElementRequest $payload,
        Context $context,
    ): Response {
        $mutation = new ReplaceElement($this->registry, $payload->elementId, $payload->newType, $this->bindingRegistry, $this->bindingApplicator);

        return $this->respond($mutation, $payload->layout, $payload->rootSource, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/duplicate-element', name: 'api.action.content_system.layout.duplicate_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function duplicate(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        DuplicateElementRequest $payload,
        Context $context,
    ): Response {
        return $this->respond(new DuplicateElement($payload->elementId, $payload->index), $payload->layout, $payload->rootSource, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/wrap-elements', name: 'api.action.content_system.layout.wrap_elements', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function wrap(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        WrapElementsRequest $payload,
        Context $context,
    ): Response {
        $mutation = new WrapElements($this->registry, $payload->elementIds, $payload->containerType, $payload->slot);

        return $this->respond($mutation, $payload->layout, $payload->rootSource, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/unwrap-element', name: 'api.action.content_system.layout.unwrap_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function unwrap(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        UnwrapElementRequest $payload,
        Context $context,
    ): Response {
        return $this->respond(new UnwrapElement($payload->containerElementId), $payload->layout, $payload->rootSource, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/attach-element', name: 'api.action.content_system.layout.attach_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function attach(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        AttachElementRequest $payload,
        Context $context,
    ): Response {
        $mutation = new AttachElement($this->registry, $this->decoder->decodeOne($payload->element), $payload->parentElementId, $payload->slot, $payload->index);

        return $this->respond($mutation, $payload->layout, $payload->rootSource, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/bind-element', name: 'api.action.content_system.layout.bind_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function bind(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        BindElementRequest $payload,
        Context $context,
    ): Response {
        $mutation = new BindElement($this->bindingRegistry, $payload->bindingSpecificationId, $payload->elementId, $this->bindingApplicator);

        return $this->respond($mutation, $payload->layout, $payload->rootSource, $context);
    }

    /**
     * @param array<int|string, mixed> $layout
     */
    private function respond(LayoutMutation $mutation, array $layout, ?string $rootSource, Context $context): JsonResponse
    {
        $tree = new StoredTree($this->decoder->decode($layout));
        $rootContext = $this->rootSourceRegistry->resolveGated($rootSource, $context);
        $result = $this->pipeline->run($mutation, $tree, $rootContext);

        return new JsonResponse(MutationResponse::fromResult($result, $this->elementCodec));
    }
}
