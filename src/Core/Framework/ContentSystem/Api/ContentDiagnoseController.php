<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSection;
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
 * Resolve-and-diagnose action: given a draft layout tree from the request (and optionally a bound source via
 * entityType/section), it returns the per-element resolutions plus the diagnostics report without persisting.
 * Operates only on the draft tree in the request; it never reads or writes the stored content_layout entity.
 * Serves the editor's after-local-edit "what is broken / still unresolved" need and agent layout linting. The
 * admin Context is passed straight through; no SalesChannelContext is built, because the binding computation
 * needs only Context.
 *
 * @internal
 *
 * @final
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ContentDiagnoseController
{
    public function __construct(
        private readonly DraftLayoutDecoder $decoder,
        private readonly LayoutDiagnostics $diagnostics,
        private readonly SpecificationSourceLocator $sourceLocator,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/diagnose', name: 'api.action.content_system.layout.diagnose', methods: [Request::METHOD_POST])]
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
        if ($payload->entityType !== null && $payload->entityType !== '') {
            return $this->sourceLocator->resolveByEntityType($payload->entityType)->providedRootContext($context);
        }

        if ($payload->section !== null && $payload->section !== '') {
            $section = ContentSection::tryFrom($payload->section) ?? throw ContentSystemException::noSourceForSection($payload->section);

            return $this->sourceLocator->resolveBySection($section)->providedRootContext($context);
        }

        return null;
    }
}
