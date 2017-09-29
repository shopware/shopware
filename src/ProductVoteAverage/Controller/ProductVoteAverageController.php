<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\ProductVoteAverage\Repository\ProductVoteAverageRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_vote_average_ro.api_controller", path="/api")
 */
class ProductVoteAverageController extends ApiController
{
    /**
     * @var ProductVoteAverageRepository
     */
    private $productVoteAverageRepository;

    public function __construct(ProductVoteAverageRepository $productVoteAverageRepository)
    {
        $this->productVoteAverageRepository = $productVoteAverageRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'productVoteAverages';
    }

    public function getXmlChildKey(): string
    {
        return 'productVoteAverage';
    }

    /**
     * @Route("/productVoteAverage.{responseFormat}", name="api.productVoteAverage.list", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function listAction(Request $request, ApiContext $context): Response
    {
        $criteria = new Criteria();

        if ($request->query->has('offset')) {
            $criteria->setOffset((int) $request->query->get('offset'));
        }

        if ($request->query->has('limit')) {
            $criteria->setLimit((int) $request->query->get('limit'));
        }

        if ($request->query->has('query')) {
            $criteria->addFilter(
                QueryStringParser::fromUrl($request->query->get('query'))
            );
        }

        $criteria->setFetchCount(true);

        $productVoteAverages = $this->productVoteAverageRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productVoteAverages, 'total' => $productVoteAverages->getTotal()],
            $context
        );
    }

    /**
     * @Route("/productVoteAverage/{productVoteAverageUuid}.{responseFormat}", name="api.productVoteAverage.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productVoteAverageUuid');
        $productVoteAverages = $this->productVoteAverageRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $productVoteAverages->get($uuid)], $context);
    }
}
