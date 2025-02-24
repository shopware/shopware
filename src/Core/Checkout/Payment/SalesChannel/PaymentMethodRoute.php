<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Checkout\Payment\Hook\PaymentMethodRouteHook;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class PaymentMethodRoute extends AbstractPaymentMethodRoute
{
    final public const ALL_TAG = 'payment-method-route';

    /**
     * @internal
     *
     * @param SalesChannelRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $paymentMethodRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ScriptExecutor $scriptExecutor
    ) {
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'payment-method-route-' . $salesChannelId;
    }

    #[Route(
        path: '/store-api/payment-method',
        name: 'store-api.payment.method',
        defaults: ['_entity' => 'payment_method'],
        methods: ['GET', 'POST']
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $this->dispatcher->dispatch(new AddCacheTagEvent(
            self::buildName($context->getSalesChannelId())
        ));

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'))
            ->addAssociation('media');

        $result = $this->paymentMethodRepository->search($criteria, $context);

        $paymentMethods = $result->getEntities();

        $paymentMethods->sortPaymentMethodsByPreference($context);

        $result->assign(['entities' => $paymentMethods, 'elements' => $paymentMethods->getElements(), 'total' => $paymentMethods->count()]);

        $this->scriptExecutor->execute(new PaymentMethodRouteHook(
            $paymentMethods,
            $context,
        ));

        return new PaymentMethodRouteResponse($result);
    }
}
