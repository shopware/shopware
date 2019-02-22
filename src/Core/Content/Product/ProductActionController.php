<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\Util\VariantGenerator;
use Shopware\Core\Framework\Context;
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

    public function __construct(VariantGenerator $generator)
    {
        $this->generator = $generator;
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
}
