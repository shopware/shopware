<?php declare(strict_types=1);

namespace Shopware\Rest\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Content\Product\Definition\ProductDefinition;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Product\Service\VariantGenerator;
use Shopware\Rest\Context\RestContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
     * @Route("/api/v1/product/{productId}/actions/generate-variants", name="api.product.actions.generate-variants")
     * @Method({"POST"})
     *
     * @param string             $productId
     * @param ApplicationContext $context
     *
     * @throws \Shopware\Product\Exception\NoConfiguratorFoundException
     * @throws \Shopware\Product\Exception\ProductNotFoundException
     *
     * @return JsonResponse
     */
    public function generateVariants(Request $request, string $productId, RestContext $context): JsonResponse
    {
        $offset = $request->query->get('offset', null);
        $limit = $request->query->get('limit', null);

        $events = $this->generator->generate($productId, $context->getApplicationContext(), $offset, $limit);

        $event = $events->getEventByDefinition(ProductDefinition::class);

        return new JsonResponse(
            ['data' => $event->getIds()]
        );
    }
}
