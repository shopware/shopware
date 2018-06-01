<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Framework\Context;
use Shopware\Content\Product\ProductDefinition;
use Shopware\Content\Product\Util\VariantGenerator;
use Shopware\Framework\Api\Context\RestContext;
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
     * @param \Shopware\Framework\Context $context
     *
     * @throws \Shopware\Content\Product\Exception\NoConfiguratorFoundException
     * @throws \Shopware\Content\Product\Exception\ProductNotFoundException
     *
     * @return JsonResponse
     */
    public function generateVariants(Request $request, string $productId, RestContext $context): JsonResponse
    {
        $offset = $request->query->get('offset', null);
        $limit = $request->query->get('limit', null);

        $events = $this->generator->generate($productId, $context->getContext(), $offset, $limit);

        $event = $events->getEventByDefinition(ProductDefinition::class);

        return new JsonResponse(
            ['data' => $event->getIds()]
        );
    }
}
