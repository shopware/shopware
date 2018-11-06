<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Api\Response\Type\Api\JsonType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\System\Tax\TaxStruct;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PriceActionController extends AbstractController
{
    /**
     * @var RepositoryInterface
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
        RepositoryInterface $taxRepository,
        NetPriceCalculator $netCalculator,
        GrossPriceCalculator $grossCalculator
    ) {
        $this->taxRepository = $taxRepository;
        $this->netCalculator = $netCalculator;
        $this->grossCalculator = $grossCalculator;
    }

    /**
     * @Route("/api/v{version}/price/actions/calculate", name="api.price.actions.calculate", methods={"POST"})
     *
     * @param Request $request
     * @param Context $context
     *
     * @return JsonResponse
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

        $taxes = $this->taxRepository->read(new ReadCriteria([$taxId]), $context);
        $tax = $taxes->get($taxId);
        if (!$tax instanceof TaxStruct) {
            throw new \InvalidArgumentException(sprintf('Tax rule with id %s not found taxId missing', $taxId));
        }

        $calculator = $this->grossCalculator;
        if ($output === 'net') {
            $calculator = $this->netCalculator;
        }

        $definition = new QuantityPriceDefinition(
            $price,
            new TaxRuleCollection([new PercentageTaxRule($tax->getTaxRate(), 100)]),
            $quantity,
            $preCalculated
        );

        $calculated = $calculator->calculate($definition);

        $data = json_decode(json_encode($calculated, JSON_PRESERVE_ZERO_FRACTION), true);

        return new JsonResponse(
            ['data' => JsonType::format($data)]
        );
    }
}
