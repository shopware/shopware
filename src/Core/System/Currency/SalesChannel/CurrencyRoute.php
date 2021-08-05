<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CurrencyRoute extends AbstractCurrencyRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $currencyRepository;

    public function __construct(SalesChannelRepositoryInterface $currencyRepository)
    {
        $this->currencyRepository = $currencyRepository;
    }

    public function getDecorated(): AbstractCurrencyRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("currency")
     * @OA\Post(
     *      path="/currency",
     *      summary="Fetch currencies",
     *      description="Perform a filtered search for currencies.",
     *      operationId="readCurrency",
     *      tags={"Store API", "System & Context"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Entity search result containing currencies.",
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(ref="#/components/schemas/EntitySearchResult"),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          type="array",
     *                          property="elements",
     *                          @OA\Items(ref="#/components/schemas/Currency")
     *                      )
     *                  )
     *              }
     *          )
     *     )
     * )
     * @Route("/store-api/currency", name="store-api.currency", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): CurrencyRouteResponse
    {
        /** @var CurrencyCollection $currencyCollection */
        $currencyCollection = $this->currencyRepository->search($criteria, $context)->getEntities();

        return new CurrencyRouteResponse($currencyCollection);
    }
}
