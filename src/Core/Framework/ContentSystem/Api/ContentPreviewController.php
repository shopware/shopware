<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRoute;
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
 * Mints a short-lived, openable URL for a draft content layout, without persisting the layout.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ContentPreviewController
{
    /**
     * The same refusal {@see ContentRoute} makes: field selection is not part of the content contract, and a
     * draft admitted here is rendered by the same pipeline the store-api routes serve from. The list is
     * repeated rather than shared because a shared holder would have to be a service, and neither surface
     * takes one for this.
     */
    private const FIELD_SELECTION_PARAMETERS = ['includes', 'excludes'];

    /**
     * @internal
     */
    public function __construct(
        private readonly ContentPreviewPageBuilder $previewPageBuilder,
        private readonly ContentPreviewPayloadStore $payloadStore,
    ) {
    }

    /**
     * The draft is admitted through the same {@see ContentPreviewPageBuilder::build()} that redeeming the
     * token runs, and its page is discarded: minting a token is a promise that redeeming it renders, and the
     * only way to keep that promise without a second copy of the gate is to run the one gate. A draft the
     * builder refuses is a 400 and never reaches the store.
     */
    #[Route(path: '/api/_action/content-system/preview/entity/url', name: 'api.action.content_system.preview.entity.url', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function previewUrl(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentPreviewRequest $payload,
        Request $request,
        Context $context,
    ): JsonResponse {
        $this->rejectFieldSelection($request);

        $this->previewPageBuilder->build($payload, $context);

        $token = $this->payloadStore->store($payload);
        $url = \sprintf(
            '%s%s/content-system/preview/%s',
            $request->getSchemeAndHttpHost(),
            rtrim($request->getBaseUrl(), '/'),
            $token,
        );

        return new JsonResponse([
            'url' => $url,
        ]);
    }

    /**
     * All three bags, because the framework's field-selection helper reads all three, and this action is a
     * POST whose parameters arrive in the request bag.
     */
    private function rejectFieldSelection(Request $request): void
    {
        foreach (self::FIELD_SELECTION_PARAMETERS as $parameter) {
            if ($request->attributes->has($parameter) || $request->query->has($parameter) || $request->request->has($parameter)) {
                throw ContentSystemException::fieldSelectionNotSupported($parameter);
            }
        }
    }
}
