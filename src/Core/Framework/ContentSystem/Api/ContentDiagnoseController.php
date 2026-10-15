<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
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
 * No SalesChannelContext is built; diagnosis needs only the admin Context, passed straight through.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ContentDiagnoseController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DraftLayoutDecoder $decoder,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly LayoutDiagnostics $diagnostics,
        private readonly ContentPreviewPageBuilder $previewPageBuilder,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/diagnose', name: 'api.action.content_system.layout.diagnose', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function diagnose(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentDiagnoseRequest $payload,
        Context $context,
    ): Response {
        [$tree, $decodeViolations] = $this->decoder->decodeLintable($payload->layout);

        $rootSource = $payload->runtimeContext === null
            ? $payload->rootSource
            : $payload->runtimeContext->entityType;
        if ($payload->runtimeContext !== null && $payload->rootSource !== null && $payload->rootSource !== $payload->runtimeContext->entityType) {
            throw ContentSystemException::unknownRootSource($payload->rootSource);
        }

        $rootContext = $this->rootSourceRegistry->resolveGated($rootSource, $context);

        // resolveGated() has already rejected a non-empty source that is not a member, so anything still
        // here is either a registered id or absent — and an absent one means the mapping checks are skipped
        // along with every other binding-scope check.
        $analysis = $this->diagnostics->analyze($tree, $rootContext, $rootSource);

        $runtimeViolations = [];
        if ($payload->runtimeContext !== null) {
            $runtime = $payload->runtimeContext;
            $render = $this->previewPageBuilder->build(new ContentPreviewRequest(
                layout: $payload->layout,
                entityType: $runtime->entityType,
                entityId: $runtime->entityId,
                salesChannelId: $runtime->salesChannelId,
                languageId: $runtime->languageId,
                currencyId: $runtime->currencyId,
                domainId: $runtime->domainId,
                customerId: $runtime->customerId,
                queryParameters: $runtime->queryParameters,
            ), $context);
            $runtimeViolations = $render['result']->mappingDiagnostics;
        }

        $report = new DiagnosticsReport([...$decodeViolations, ...$analysis->report->violations, ...$runtimeViolations]);

        return new JsonResponse(DiagnoseResponse::fromReport($analysis->resolutions, $report));
    }
}
