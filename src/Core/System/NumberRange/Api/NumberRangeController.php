<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Api;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class NumberRangeController extends AbstractController
{
    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $valueGenerator;

    public function __construct(
        NumberRangeValueGeneratorInterface $valueGenerator
    ) {
        $this->valueGenerator = $valueGenerator;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/number-range/reserve/{type}/{saleschannel?}", name="api.action.number-range.reserve", methods={"GET"})
     * @Cache(mustRevalidate=true)
     */
    public function reserve(string $type, ?string $saleschannel, Context $context, Request $request): JsonResponse
    {
        $generatedNumber = $this->valueGenerator->getValue($type, $context, $saleschannel, $request->query->getBoolean('preview'));

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/number-range/preview-pattern/{type}", defaults={"type"="default"}, name="api.action.number-range.preview-pattern", methods={"GET"})
     * @Cache(mustRevalidate=true)
     */
    public function previewPattern(string $type, Request $request): JsonResponse
    {
        $generatedNumber = $this->valueGenerator->previewPattern(
            $type,
            $request->query->has('pattern') ? (string) $request->query->get('pattern') : null,
            (int) $request->query->get('start')
        );

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }
}
