<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentLayoutValidator;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Format\AbstractResponseFactory;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Previews how a draft content layout renders with real entity data, without persisting the layout.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ContentPreviewController
{
    public function __construct(
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly ContentElementFieldSerializer $elementSerializer,
        private readonly ContentLayoutValidator $layoutValidator,
        private readonly ContentPipeline $contentPipeline,
        private readonly AbstractResponseFactory $responseFactory,
    ) {
    }

    #[Route(path: '/api/_action/content-system/preview/entity', name: 'api.action.content_system.preview.entity', methods: [Request::METHOD_POST])]
    public function preview(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentPreviewRequest $payload,
        Context $context,
    ): Response {
        $salesChannelContext = $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters(
                $payload->salesChannelId,
                Random::getAlphanumericString(32),
                $payload->languageId,
                $payload->currencyId,
                $payload->domainId,
                $context,
                $payload->customerId,
            )
        );

        $request = new Request($payload->queryParameters);

        $specification = $this->specificationResolver->resolveWithoutLayout(
            $payload->entityType,
            $payload->entityId,
            $request,
            $salesChannelContext,
        );

        $elements = $this->decodeLayout($payload->layout);

        $violations = $this->layoutValidator->validate($elements);
        if ($violations->count() > 0) {
            throw ContentSystemException::elementTypesInvalid($violations);
        }

        $renderableLayout = RenderableLayout::create(
            LayoutReference::create(Uuid::randomHex(), 'preview', null),
            $elements,
        );

        $contentPage = $this->contentPipeline->load(
            $renderableLayout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::FULL,
            $salesChannelContext,
        );

        return $this->responseFactory->createResponse($contentPage);
    }

    /**
     * Structural pre-decode gate: every layout element must be an array with a non-empty string
     * id and component. Runs in full before decodeElement(), so a malformed element is a 400 client
     * error instead of a 500 from the field serializer's id/component guards.
     *
     * @param array<int|string, mixed> $layout
     *
     * @return list<ContentElement>
     */
    private function decodeLayout(array $layout): array
    {
        $violations = new ConstraintViolationList();
        $decodable = [];

        foreach ($layout as $index => $element) {
            $path = '[' . $index . ']';

            if (!\is_array($element)) {
                $violations->add($this->structuralViolation($path, 'Layout element must be an array.', $element));

                continue;
            }

            $id = $element['id'] ?? null;
            $component = $element['component'] ?? null;
            $valid = true;

            if (!\is_string($id) || $id === '') {
                $violations->add($this->structuralViolation($path . '.id', 'Layout element id must be a non-empty string.', $id));
                $valid = false;
            }

            if (!\is_string($component) || $component === '') {
                $violations->add($this->structuralViolation($path . '.component', 'Layout element component must be a non-empty string.', $component));
                $valid = false;
            }

            if ($valid) {
                $decodable[] = $element;
            }
        }

        if ($violations->count() > 0) {
            throw ContentSystemException::invalidLayoutStructure($violations);
        }

        return array_map(
            fn (array $element): ContentElement => $this->elementSerializer->decodeElement($element),
            $decodable,
        );
    }

    private function structuralViolation(string $propertyPath, string $message, mixed $invalidValue): ConstraintViolation
    {
        return new ConstraintViolation($message, null, [], null, $propertyPath, $invalidValue);
    }
}
