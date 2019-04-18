<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Account\PaymentMethod;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class AccountPaymentMethodPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'account-payment-method.pagelet.loaded';

    /**
     * @var EntitySearchResult
     */
    protected $pagelet;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(
        EntitySearchResult $pagelet,
        SalesChannelContext $context,
        Request $request
    ) {
        $this->pagelet = $pagelet;
        $this->context = $context;
        $this->request = $request;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getPagelet(): EntitySearchResult
    {
        return $this->pagelet;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
