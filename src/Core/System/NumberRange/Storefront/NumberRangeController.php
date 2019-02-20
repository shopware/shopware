<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Storefront;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class NumberRangeController extends AbstractController
{
    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;
    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $valueGenerator;

    public function __construct(
        DefinitionRegistry $definitionRegistry,
        NumberRangeValueGeneratorInterface $valueGenerator
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->valueGenerator = $valueGenerator;
    }

    /**
     * @Route("/storefront-api/v{version}/number-range/reserve/{entity}", name="storefront-api.number-range.reserve", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function reserve(string $entity, CheckoutContext $checkoutContext)
    {
        $entityDefinition = $this->definitionRegistry->get($entity);
        $generatedNumber = $this->valueGenerator->getValue($entityDefinition, $checkoutContext);

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }
}
