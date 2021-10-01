<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Api;

use OpenApi\Annotations as OA;
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
     * @OA\Get(
     *     path="/_action/number-range/reserve/{type}/{saleschannel?}",
     *     summary="Reserve or preview a document number",
     *     description="This endpoint provides functionality to reserve or preview a document number which can be used to create a new document using the `/_action/order/{orderId}/document/{documentTypeName}` endpoint.

The number generated by the endpoint will be reserved and the number pointer will be incremented with every call. For preview purposes, you can add the `?preview=1` parameter to the request. In that case, the number will not be incremented.",
     *     operationId="numberRangeReserve",
     *     tags={"Admin API", "Document Management"},
     *     @OA\Parameter(
     *         name="type",
     *         description="`technicalName` of the document type (e.g. `document_invoice`). Available types can be fetched with the `/api/document-type endpoint`.",
     *         @OA\Schema(type="string"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="saleschannel",
     *         description="Sales channel for the number range. Number ranges can be defined per sales channel, so you can pass a sales channel ID here.",
     *         @OA\Schema(type="string"),
     *         in="path",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="preview",
     *         description="If this parameter has a true value, the number will not actually be incremented, but only previewed.",
     *         @OA\Schema(type="boolean"),
     *         in="query",
     *         required=false
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The generated number",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="number",
     *                 description="The generated (or previewed) document number.",
     *                 type="string"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Number range not found"
     *     )
     * )
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
