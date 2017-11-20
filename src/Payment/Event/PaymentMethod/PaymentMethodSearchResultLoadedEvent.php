<?php declare(strict_types=1);

namespace Shopware\Payment\Event\PaymentMethod;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Payment\Struct\PaymentMethodSearchResult;

class PaymentMethodSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'payment_method.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
