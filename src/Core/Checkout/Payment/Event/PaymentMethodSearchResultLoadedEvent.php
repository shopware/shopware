<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Event;

use Shopware\Core\Checkout\Payment\Struct\PaymentMethodSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class PaymentMethodSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method.search.result.loaded';

    /**
     * @var PaymentMethodSearchResult
     */
    protected $result;

    public function __construct(PaymentMethodSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
