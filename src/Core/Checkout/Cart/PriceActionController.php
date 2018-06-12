<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Api\Context\RestContext;
use Shopware\Core\Framework\Api\Response\Type\JsonType;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Serializer;

class PriceActionController extends Controller
{
    /**
     * @var Serializer
     */
    private $serializer;

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
        Serializer $serializer,
        RepositoryInterface $taxRepository,
        NetPriceCalculator $netCalculator,
        GrossPriceCalculator $grossCalculator
    ) {
        $this->serializer = $serializer;
        $this->taxRepository = $taxRepository;
        $this->netCalculator = $netCalculator;
        $this->grossCalculator = $grossCalculator;
    }

    /**
     * @Route("/api/v1/price/actions/calculate", name="api.price.actions.calculate")
     * @Method({"POST"})
     *
     * @param Request     $request
     * @param RestContext $context
     *
     * @return JsonResponse
     */
    public function calculate(Request $request, RestContext $context): JsonResponse
    {
        $post = $this->getPost($request);

        if (!array_key_exists('price', $post)) {
            throw new \InvalidArgumentException('Parameter price missing');
        }
        if (!array_key_exists('taxId', $post)) {
            throw new \InvalidArgumentException('Parameter taxId missing');
        }

        $taxId = $post['taxId'];
        $price = (float) $post['price'];
        $quantity = (int) ($post['quantity'] ?? 1);
        $output = $post['output'] ?? 'gross';
        $preCalculated = (bool) ($post['calculated'] ?? true);

        $taxes = $this->taxRepository->read(new ReadCriteria([$taxId]), $context->getContext());
        $tax = $taxes->get($taxId);
        if (!$tax) {
            throw new \InvalidArgumentException(sprintf('Tax rule with id %s not found taxId missing', $taxId));
        }

        $calculator = $this->grossCalculator;
        if ($output === 'net') {
            $calculator = $this->netCalculator;
        }

        $definition = new PriceDefinition(
            $price,
            new TaxRuleCollection([new PercentageTaxRule($tax->getRate(), 100)]),
            $quantity,
            $preCalculated
        );

        $calculated = $calculator->calculate($definition, $context);

        $data = json_decode(json_encode($calculated, JSON_PRESERVE_ZERO_FRACTION), true);

        return new JsonResponse(
            ['data' => JsonType::format($data)]
        );
    }

    private function getPost(Request $request): array
    {
        if (empty($request->getContent())) {
            return [];
        }

        return $this->serializer->decode($request->getContent(), 'json');
    }
}
