<?php declare(strict_types=1);

namespace Shopware\Rest\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Context\Struct\ShopContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class ProductActionController extends Controller
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @Route("/api/product/{id}/actions/generate-variants/{offset}", name="sync.api")
     * @Method({"POST"})
     *
     * @param int $offset
     */
    public function generateVariants(string $id, int $offset, ShopContext $context): void
    {
    }
}
