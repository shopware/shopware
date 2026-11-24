<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\BindElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\TranslateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UpdateElementProperties;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Shopware\Core\Framework\ContentSystem\Mutation\PersistedLayoutMutator;
use Shopware\Core\Framework\ContentSystem\Mutation\PropertyMappingMutationFactory;
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
 * The persisted counterpart to {@see LayoutMutationController}. Every action has a live route, guarded by
 * `expectedVersion` (the layout's updatedAt), and a branch route under `branches/{branchId}`, guarded by
 * `expectedHead` (the branch head revision id); each route rejects the other's token.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ContentLayoutMutationController
{
    private const ID_PATTERN = '[0-9a-f]{32}';

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
        private readonly PropertyMappingMutationFactory $propertyMappingMutations,
        private readonly StoredMappingInspector $mappingInspector,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/insert-element', name: 'api.action.content_system.layout.persisted_insert_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/insert-element', name: 'api.action.content_system.layout.branch_insert_element', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function insert(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutInsertRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        $mutation = new InsertElement($this->registry, $payload->type, $this->bindingRegistry, $this->bindingApplicator, bindingSpecificationId: $payload->bindingSpecificationId, parentElementId: $payload->parentElementId, index: $payload->index, slot: $payload->slot);

        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/remove-element', name: 'api.action.content_system.layout.persisted_remove_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/remove-element', name: 'api.action.content_system.layout.branch_remove_element', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function remove(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutRemoveRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, new RemoveElement($payload->elementId), $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/move-element', name: 'api.action.content_system.layout.persisted_move_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/move-element', name: 'api.action.content_system.layout.branch_move_element', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function move(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutMoveRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        $mutation = new MoveElement($payload->elementId, $payload->newParentId, $payload->newSlot, $payload->index);

        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/replace-element', name: 'api.action.content_system.layout.persisted_replace_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/replace-element', name: 'api.action.content_system.layout.branch_replace_element', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function replace(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutReplaceRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        return $this->respond(
            $layoutId,
            $branchId,
            $payload->expectedVersion,
            $payload->expectedHead,
            new ReplaceElement(
                $this->registry,
                $payload->elementId,
                $payload->newType,
                $this->bindingRegistry,
                $this->bindingApplicator,
                $this->mappingInspector,
            ),
            $context,
        );
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/duplicate-element', name: 'api.action.content_system.layout.persisted_duplicate_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/duplicate-element', name: 'api.action.content_system.layout.branch_duplicate_element', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function duplicate(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutDuplicateRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, new DuplicateElement($payload->elementId, $payload->index), $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/wrap-elements', name: 'api.action.content_system.layout.persisted_wrap_elements', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/wrap-elements', name: 'api.action.content_system.layout.branch_wrap_elements', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function wrap(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutWrapElementsRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        $mutation = new WrapElements($this->registry, $payload->elementIds, $payload->containerType, $payload->slot);

        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/unwrap-element', name: 'api.action.content_system.layout.persisted_unwrap_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/unwrap-element', name: 'api.action.content_system.layout.branch_unwrap_element', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function unwrap(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutUnwrapRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, new UnwrapElement($payload->containerElementId), $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/attach-element', name: 'api.action.content_system.layout.persisted_attach_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/attach-element', name: 'api.action.content_system.layout.branch_attach_element', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function attach(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutAttachRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        $mutation = new AttachElement($this->registry, $this->decoder->decodeOne($payload->element), $this->bindingRegistry, $this->bindingApplicator, $payload->parentElementId, $payload->slot, $payload->index);

        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/bind-element', name: 'api.action.content_system.layout.persisted_bind_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/bind-element', name: 'api.action.content_system.layout.branch_bind_element', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function bind(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutBindRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        $mutation = new BindElement($this->bindingRegistry, $payload->bindingSpecificationId, $payload->elementId, $this->bindingApplicator);

        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/update-element-properties', name: 'api.action.content_system.layout.persisted_update_element_properties', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/update-element-properties', name: 'api.action.content_system.layout.branch_update_element_properties', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function updateProperties(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutUpdateElementPropertiesRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        $mutation = new UpdateElementProperties($this->registry, $payload->elementId, $payload->values, $payload->removeKeys);

        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/translate-element', name: 'api.action.content_system.layout.persisted_translate_element', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read', 'content_layout:translate']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/translate-element', name: 'api.action.content_system.layout.branch_translate_element', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read', 'content_layout:translate']], methods: [Request::METHOD_POST])]
    public function translate(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutTranslateElementRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        $mutation = new TranslateElement($this->registry, $payload->elementId, $payload->values);

        return $this->respond($layoutId, $branchId, $payload->expectedVersion, $payload->expectedHead, $mutation, $context);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/map-property', name: 'api.action.content_system.layout.persisted_map_property', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/map-property', name: 'api.action.content_system.layout.branch_map_property', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function mapProperty(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutMapPropertyRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        return $this->respond(
            $layoutId,
            $branchId,
            $payload->expectedVersion,
            $payload->expectedHead,
            $this->propertyMappingMutations->map(
                $payload->elementId,
                $payload->propertyKey,
                MappingSourceRequestDecoder::decode($payload->source),
            ),
            $context,
        );
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/unmap-property', name: 'api.action.content_system.layout.persisted_unmap_property', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/unmap-property', name: 'api.action.content_system.layout.branch_unmap_property', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function unmapProperty(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutUnmapPropertyRequest $payload,
        Context $context,
        ?string $branchId = null,
    ): Response {
        return $this->respond(
            $layoutId,
            $branchId,
            $payload->expectedVersion,
            $payload->expectedHead,
            $this->propertyMappingMutations->unmap($payload->elementId, $payload->propertyKey),
            $context,
        );
    }

    private function respond(string $layoutId, ?string $branchId, ?string $expectedVersion, ?string $expectedHead, LayoutMutation $mutation, Context $context): JsonResponse
    {
        if ($branchId === null) {
            if ($expectedHead !== null) {
                throw ContentSystemException::unknownRequestField(['expectedHead']);
            }

            $result = $this->mutator->mutate($layoutId, $expectedVersion, $mutation, $context);

            return new JsonResponse(MutationResponse::fromResult($result, $this->elementCodec));
        }

        if ($expectedVersion !== null) {
            throw ContentSystemException::unknownRequestField(['expectedVersion']);
        }

        $result = $this->mutator->mutateBranch($layoutId, $branchId, $expectedHead, $mutation, $context);

        return new JsonResponse([
            ...MutationResponse::fromResult($result->mutation, $this->elementCodec)->jsonSerialize(),
            'revision' => LayoutRevisionNormalizer::revision($result->revision, [$result->branch->id], false),
            'branch' => LayoutRevisionNormalizer::branch($result->branch),
        ]);
    }
}
