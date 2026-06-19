<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Resolve-and-diagnose action: given a layout tree (and optionally a bound source via entityType/section), it
 * returns the per-element resolutions plus the diagnostics report without persisting. Serves the editor's
 * after-local-edit "what is broken / still unresolved" need and agent layout linting. The admin Context is
 * passed straight through; no SalesChannelContext is built, because the binding computation needs only Context.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ContentLayoutDiagnosticsController
{
    public function __construct(
        private readonly ContentElementFieldSerializer $elementSerializer,
        private readonly LayoutDiagnostics $diagnostics,
        private readonly SpecificationSourceResolver $sourceResolver,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/diagnose', name: 'api.action.content_system.layout.diagnose', methods: [Request::METHOD_POST])]
    public function diagnose(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutDiagnoseRequest $payload,
        Context $context,
    ): Response {
        [$tree, $decodeViolations] = $this->decodeLayout($payload->layout);

        $analysis = $this->diagnostics->analyze($tree, $this->resolveRootContext($payload, $context), $context);

        $report = new DiagnosticsReport([...$decodeViolations, ...$analysis->report->violations]);

        return new JsonResponse([
            'resolutions' => $this->serializeResolutions($analysis),
            'diagnostics' => $this->serializeReport($report),
        ]);
    }

    /**
     * @param array<int|string, mixed> $layout
     *
     * @return array{0: list<ContentElement>, 1: list<Violation>}
     */
    private function decodeLayout(array $layout): array
    {
        $structural = new ConstraintViolationList();
        $tree = [];
        $decodeViolations = [];

        foreach ($layout as $index => $element) {
            if (!\is_array($element)) {
                $structural->add($this->structuralViolation('[' . $index . ']', 'Layout element must be an array.', $element));

                continue;
            }

            $id = $element['id'] ?? null;
            $component = $element['component'] ?? null;

            if (!\is_string($id) || $id === '' || !\is_string($component) || $component === '') {
                $structural->add($this->structuralViolation('[' . $index . ']', 'Layout element requires a non-empty string id and component.', $element));

                continue;
            }

            $decoded = $this->decodeElement($element, $id, $decodeViolations);

            if ($decoded !== null) {
                $tree[] = $decoded;
            }
        }

        if ($structural->count() > 0) {
            throw ContentSystemException::invalidLayoutStructure($structural);
        }

        return [$tree, $decodeViolations];
    }

    /**
     * @param array<string, mixed> $element
     * @param list<Violation> $decodeViolations
     */
    private function decodeElement(array $element, string $id, array &$decodeViolations): ?ContentElement
    {
        try {
            return $this->elementSerializer->decodeElement($element);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            $decodeViolations[] = new Violation(ViolationCode::InvalidConfig, $id, null, $exception->getMessage());

            return null;
        }
    }

    /**
     * @return list<ProvidedContext>|null
     */
    private function resolveRootContext(ContentLayoutDiagnoseRequest $payload, Context $context): ?array
    {
        if ($payload->entityType !== null && $payload->entityType !== '') {
            return $this->sourceResolver->resolveByEntityType($payload->entityType)->providedRootContext($context);
        }

        if ($payload->section !== null && $payload->section !== '') {
            $section = ContentSection::tryFrom($payload->section) ?? throw ContentSystemException::noSourceForSection($payload->section);

            return $this->sourceResolver->resolveBySection($section)->providedRootContext($context);
        }

        return null;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function serializeResolutions(LayoutAnalysis $analysis): array
    {
        return array_map(
            fn (array $resolutions): array => array_map($this->serializeResolution(...), $resolutions),
            $analysis->resolutions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResolution(PropertyResolution $resolution): array
    {
        return [
            'key' => $resolution->key,
            'kind' => $resolution->kind->value,
            'required' => $resolution->required,
            'type' => $resolution->type,
            'default' => $resolution->default,
            'fqcn' => $resolution->fqcn,
            'resolved' => $resolution->resolved === null ? null : $this->serializeCandidate($resolution->resolved),
            'candidates' => array_map($this->serializeCandidate(...), $resolution->candidates),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCandidate(ResolutionCandidate $candidate): array
    {
        return [
            'via' => $candidate->via->value,
            'contextKey' => $candidate->contextKey,
            'providerElementId' => $candidate->providerElementId,
            'path' => $candidate->path,
            'distribution' => $candidate->distribution?->value,
            'contextType' => $candidate->contextType?->value,
            'loaderSource' => $candidate->loaderSource,
            'configTemplate' => $candidate->configTemplate,
            'configComplete' => $candidate->configComplete,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReport(DiagnosticsReport $report): array
    {
        return [
            'wellFormed' => $report->isWellFormed(),
            'resolvable' => $report->isResolvable(),
            'violations' => array_map($this->serializeViolation(...), $report->violations),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeViolation(Violation $violation): array
    {
        return [
            'code' => $violation->code->value,
            'scope' => $violation->scope()->value,
            'severity' => $violation->severity()->value,
            'elementId' => $violation->elementId,
            'key' => $violation->key,
            'message' => $violation->message,
            'candidates' => array_map($this->serializeCandidate(...), $violation->candidates),
        ];
    }

    private function structuralViolation(string $propertyPath, string $message, mixed $invalidValue): ConstraintViolation
    {
        return new ConstraintViolation($message, null, [], null, $propertyPath, $invalidValue);
    }
}
