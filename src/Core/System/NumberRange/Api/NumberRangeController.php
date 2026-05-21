<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class NumberRangeController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractNumberRangeValueGenerator $valueGenerator
    ) {
    }

    #[Cache(mustRevalidate: true)]
    #[Route(path: '/api/_action/number-range/reserve/{type}/{saleschannel?}', name: 'api.action.number-range.reserve', methods: ['GET'])]
    public function reserve(string $type, ?string $saleschannel, Context $context, Request $request): JsonResponse
    {
        $generatedNumber = $this->valueGenerator->getValue($type, $context, $saleschannel, $request->query->getBoolean('preview'));

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }

    /**
     * @deprecated tag:v6.8.0 - use previewPatternByNumberRange() with a concrete number range id instead
     */
    #[Cache(mustRevalidate: true)]
    #[Route(path: '/api/_action/number-range/preview-pattern/{type}', defaults: ['type' => 'default'], name: 'api.action.number-range.preview-pattern', methods: ['GET'])]
    public function previewPattern(string $type, Request $request): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '/api/_action/number-range/{numberRangeId}/preview-pattern')
        );

        $generatedNumber = Feature::silent(
            'v6.8.0.0',
            fn (): string => $this->valueGenerator->previewPattern(
                $type,
                $request->query->has('pattern') ? (string) $request->query->get('pattern') : null,
                (int) $request->query->get('start')
            )
        );

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }

    #[Cache(mustRevalidate: true)]
    #[Route(path: '/api/_action/number-range/{numberRangeId}/preview-pattern', name: 'api.action.number-range.preview-pattern-by-id', requirements: ['numberRangeId' => Uuid::VALID_PATTERN], methods: ['GET'])]
    public function previewPatternByNumberRange(string $numberRangeId, Request $request): JsonResponse
    {
        $generatedNumber = $this->valueGenerator->previewPatternByNumberRangeId(
            $numberRangeId,
            $request->query->has('pattern') ? (string) $request->query->get('pattern') : null,
            $request->query->has('start') ? (int) $request->query->get('start') : null
        );

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }
}
