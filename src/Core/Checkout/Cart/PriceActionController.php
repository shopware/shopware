<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\Tax\TaxEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class PriceActionController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var NetPriceCalculator
     */
    private $netCalculator;

    /**
     * @var GrossPriceCalculator
     */
    private $grossCalculator;

    public function __construct(
        EntityRepositoryInterface $taxRepository,
        NetPriceCalculator $netCalculator,
        GrossPriceCalculator $grossCalculator
    ) {
        $this->taxRepository = $taxRepository;
        $this->netCalculator = $netCalculator;
        $this->grossCalculator = $grossCalculator;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("api/_action/calculate-price", name="api.action.calculate-price", methods={"POST"})
     */
    public function calculate(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('price')) {
            throw new \InvalidArgumentException('Parameter price missing');
        }
        if (!$request->request->has('taxId')) {
            throw new \InvalidArgumentException('Parameter taxId missing');
        }

        $taxId = (string) $request->request->get('taxId');
        $price = (float) $request->request->get('price');
        $quantity = $request->request->getInt('quantity', 1);
        $output = $request->request->get('output', 'gross');
        $preCalculated = $request->request->getBoolean('calculated', true);

        $taxes = $this->taxRepository->search(new Criteria([$taxId]), $context);
        $tax = $taxes->get($taxId);
        if (!$tax instanceof TaxEntity) {
            throw new \InvalidArgumentException(sprintf('Tax rule with id %s not found taxId missing', $taxId));
        }

        $calculator = $this->grossCalculator;
        if ($output === 'net') {
            $calculator = $this->netCalculator;
        }

        $taxRules = new TaxRuleCollection([new TaxRule($tax->getTaxRate())]);

        $definition = new QuantityPriceDefinition($price, $taxRules, $quantity);
        $definition->setIsCalculated($preCalculated);

        $config = new CashRoundingConfig(50, 0.01, true);

        $calculated = $calculator->calculate($definition, $config);

        $data = json_decode(json_encode($calculated, \JSON_PRESERVE_ZERO_FRACTION), true);

        return new JsonResponse(
            ['data' => $data]
        );
    }
}
