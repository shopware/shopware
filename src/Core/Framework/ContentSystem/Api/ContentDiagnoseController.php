<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
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
 * The resolve-and-diagnose action: returns per-element resolutions plus a diagnostics report for a draft layout
 * tree from the request, without persisting and without reading or writing the stored content_layout entity.
 *
 * The admin Context is passed straight through; no SalesChannelContext is built, because the binding computation
 * needs only Context.
 *
 * @final
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ContentDiagnoseController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DraftLayoutDecoder $decoder,
        private readonly LayoutDiagnostics $diagnostics,
        private readonly RootSourceRegistry $rootSourceRegistry,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/diagnose', name: 'api.action.content_system.layout.diagnose', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function diagnose(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentDiagnoseRequest $payload,
        Context $context,
    ): Response {
        [$tree, $decodeViolations] = $this->decoder->decodeLintable($payload->layout);

        $analysis = $this->diagnostics->analyze($tree, $this->resolveRootContext($payload, $context), $context);

        $report = new DiagnosticsReport([...$decodeViolations, ...$analysis->report->violations]);

        return new JsonResponse(DiagnoseResponse::fromReport($analysis->resolutions, $report));
    }

    /**
     * @return list<ProvidedContext>|null
     */
    private function resolveRootContext(ContentDiagnoseRequest $payload, Context $context): ?array
    {
        if ($payload->rootSource === null || $payload->rootSource === '') {
            return null;
        }

        if (!\in_array($payload->rootSource, $this->rootSourceRegistry->knownRootSources(), true)) {
            throw ContentSystemException::unknownRootSource($payload->rootSource);
        }

        return $this->rootSourceRegistry->resolve($payload->rootSource, $context);
    }
}
