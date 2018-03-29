<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Event\PaymentMethod;

use Shopware\Api\Payment\Struct\PaymentMethodSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
