<?php declare(strict_types=1);

namespace Shopware\Rest\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Service\VariantGenerator;
use Shopware\Rest\RestContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProductActionController extends Controller
{
    /**
     * @var VariantGenerator
     */
    private $generator;

    public function __construct(VariantGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * @Route("/api/product/{productId}/actions/generate-variants/{offset}/{limit}", name="api.product.actions.generate-variants")
     * @Method({"POST"})
     *
     * @param string      $productId
     * @param ShopContext $context
     * @param int|null    $offset
     * @param int|null    $limit
     *
     * @throws \Shopware\Product\Exception\NoConfiguratorFoundException
     * @throws \Shopware\Product\Exception\ProductNotFoundException
     *
     * @return JsonResponse
     */
    public function generateVariants(string $productId, RestContext $context, ?int $offset = null, ?int $limit = null): JsonResponse
    {
        $events = $this->generator->generate($productId, $context->getShopContext(), $offset, $limit);

        $event = $events->getEventByDefinition(ProductDefinition::class);

        return new JsonResponse([
            'ids' => $event->getIds(),
        ]);
    }
}
