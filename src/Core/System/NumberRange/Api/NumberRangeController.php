<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('checkout')]
class NumberRangeController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly NumberRangeValueGeneratorInterface $valueGenerator)
    {
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

    #[Cache(mustRevalidate: true)]
    #[Route(path: '/api/_action/number-range/preview-pattern/{type}', defaults: ['type' => 'default'], name: 'api.action.number-range.preview-pattern', methods: ['GET'])]
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
