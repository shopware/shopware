<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Util\VariantGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProductActionController extends AbstractController
{
    /**
     * @var VariantGenerator
     */
    private $generator;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(VariantGenerator $generator, Connection $connection)
    {
        $this->generator = $generator;
        $this->connection = $connection;
    }

    /**
     * @Route("/api/v{version}/_action/product/{productId}/generate-variant", name="api.action.product.generate-variant", methods={"POST"})
     *
     * @throws Exception\NoConfiguratorFoundException
     * @throws Exception\ProductNotFoundException
     */
    public function generateVariants(Request $request, string $productId, Context $context): JsonResponse
    {
        $offset = $request->query->get('offset');
        $limit = $request->query->get('limit');

        $events = $this->generator->generate($productId, $context, $offset, $limit);

        $event = $events->getEventByDefinition(ProductDefinition::class);

        return new JsonResponse(
            ['data' => $event->getIds()]
        );
    }

    /**
     * @Route("/api/v{version}/_action/product/{productId}/combinations", name="api.action.product.combinations", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getCombinations(string $productId, Context $context)
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('LOWER(HEX(product.id))', 'product.variation_ids');
        $query->from('product');
        $query->where('product.parent_id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($productId));
        $query->andWhere('product.variation_ids IS NOT NULL');

        $combinations = $query->execute()->fetchAll();
        $combinations = FetchModeHelper::keyPair($combinations);

        foreach ($combinations as &$combination) {
            $combination = json_decode($combination, true);
        }

        return new JsonResponse($combinations);
    }
}
