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
     * @Route("/api/v{version}/product/{productId}/actions/generate-variants", name="api.product.actions.generate-variants", methods={"POST"})
     *
     * @param Request $request
     * @param string  $productId
     * @param Context $context
     *
     * @throws Exception\NoConfiguratorFoundException
     * @throws Exception\ProductNotFoundException
     *
     * @return JsonResponse
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
