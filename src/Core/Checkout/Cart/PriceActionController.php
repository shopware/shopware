<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Tax\TaxEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    public function __construct(
        EntityRepositoryInterface $taxRepository,
        NetPriceCalculator $netCalculator,
        GrossPriceCalculator $grossCalculator,
        EntityRepositoryInterface $currencyRepository
    ) {
        $this->taxRepository = $taxRepository;
        $this->netCalculator = $netCalculator;
        $this->grossCalculator = $grossCalculator;
        $this->currencyRepository = $currencyRepository;
    }

    /**
     * @Route("api/v{version}/_action/calculate-price", name="api.action.calculate-price", methods={"POST"})
     */
    public function calculate(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('price')) {
            throw new \InvalidArgumentException('Parameter price missing');
        }
        if (!$request->request->has('taxId')) {
            throw new \InvalidArgumentException('Parameter taxId missing');
        }

        $taxId = $request->request->get('taxId');
        $price = (float) $request->request->get('price');
        $quantity = $request->request->getInt('quantity', 1);
        $output = $request->request->get('output', 'gross');
        $preCalculated = $request->request->getBoolean('calculated', true);

        $precision = $this->getCurrencyPrecision($request, $context);

        $taxes = $this->taxRepository->search(new Criteria([$taxId]), $context);
        $tax = $taxes->get($taxId);
        if (!$tax instanceof TaxEntity) {
            throw new \InvalidArgumentException(sprintf('Tax rule with id %s not found taxId missing', $taxId));
        }

        $calculator = $this->grossCalculator;
        if ($output === 'net') {
            $calculator = $this->netCalculator;
        }

        $definition = new QuantityPriceDefinition(
            $price,
            new TaxRuleCollection([new TaxRule($tax->getTaxRate())]),
            $precision,
            $quantity,
            $preCalculated
        );

        $calculated = $calculator->calculate($definition);

        $data = json_decode(json_encode($calculated, JSON_PRESERVE_ZERO_FRACTION), true);

        return new JsonResponse(
            ['data' => $data]
        );
    }

    private function getCurrencyPrecision(Request $request, Context $context): int
    {
        if (!$request->request->has('currencyId')) {
            return $context->getCurrencyPrecision();
        }

        $currencyId = $request->request->get('currencyId');

        $currency = $this->currencyRepository
            ->search(new Criteria([$currencyId]), $context)
            ->get($currencyId);

        if (!$currency) {
            throw new NotFoundHttpException(sprintf('Currency for id %s not found', $currencyId));
        }

        /* @var CurrencyEntity $currency */
        return $currency->getDecimalPrecision();
    }
}
