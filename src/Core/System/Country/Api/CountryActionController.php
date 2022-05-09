<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Api;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\Country\Service\CountryAddressFormattingService;
use Shopware\Core\System\Country\Struct\CountryAddress;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class CountryActionController
{
    private CountryAddressFormattingService $countryAddressFormattingService;

    /**
     * @internal
     */
    public function __construct(CountryAddressFormattingService $countryAddressFormattingService)
    {
        $this->countryAddressFormattingService = $countryAddressFormattingService;
    }

    /**
     * @Since("6.4.10.0")
     * @OA\Post(
     *     path="/_action/country/formatting-address",
     *     summary="Render format of the address based on the given country",
     *     description="The way to display the address, it would be based on his own country",
     *     operationId="countryAddressformatting",
     *     tags={"Admin API", "Country Address"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                  "addressData",
     *                  "addressFormat"
     *             },
     *             @OA\Property(
     *                 property="addressData",
     *                 description="An associative array that is handed over to the templating engine and can be used as variables in the address format of the country.",
     *                 example={},
     *                 type="object",
     *             ),
     *             @OA\Property(
     *                 property="addressFormat",
     *                 description="The content of the address format as plain text.",
     *                 example="{{ company }} {{ department }}",
     *                 type="string",
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The rendered preview of the address format of the country.",
     *          @OA\JsonContent(
     *              type="string"
     *          )
     *     )
     * )
     * @Route("/api/_action/country/formatting-address", name="api.action.country.formatting-address", methods={"POST"})
     */
    public function renderAddressFormatting(Request $request, Context $context): JsonResponse
    {
        $addressFormatting = $this->countryAddressFormattingService->render(
            CountryAddress::createFromEntityJsonSerialize($request->get('addressData')),
            $request->get('addressFormat'),
            $context,
        );

        return new JsonResponse($addressFormatting);
    }
}
