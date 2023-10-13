<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class HandlePaymentMethodRoute extends AbstractHandlePaymentMethodRoute
{
    /**
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     *
     * @internal
     */
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly DataValidator $dataValidator,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly EntityRepository $currencyRepository,
    ) {
    }

    public function getDecorated(): AbstractHandlePaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/handle-payment', name: 'store-api.payment.handle', methods: ['GET', 'POST'])]
    public function load(Request $request, SalesChannelContext $context): HandlePaymentMethodRouteResponse
    {
        $data = [...$request->query->all(), ...$request->request->all()];
        $this->dataValidator->validate($data, $this->createDataValidation());
        $orderId = $request->get('orderId');
        $orderCurrencyId = $this->getCurrencyFromOrder($orderId, $context->getContext());

        if ($context->getCurrency()->getId() !== $orderCurrencyId) {
            $context = $this->contextService->get(
                new SalesChannelContextServiceParameters(
                    $context->getSalesChannelId(),
                    $context->getToken(),
                    $context->getContext()->getLanguageId(),
                    $orderCurrencyId,
                )
            );
        }

        $response = $this->paymentProcessor->pay(
            $request->get('orderId'),
            $request,
            $context,
            $request->get('finishUrl'),
            $request->get('errorUrl')
        );

        return new HandlePaymentMethodRouteResponse($response);
    }

    private function createDataValidation(): DataValidationDefinition
    {
        return (new DataValidationDefinition())
            ->add('orderId', new NotBlank(), new Type('string'))
            ->add('finishUrl', new Type('string'))
            ->add('errorUrl', new Type('string'));
    }

    private function getCurrencyFromOrder(string $orderId, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orders.id', $orderId));

        return $this->currencyRepository->searchIds($criteria, $context)->firstId();
    }
}
