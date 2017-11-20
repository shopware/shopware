<?php declare(strict_types=1);

namespace Shopware\Payment\Event\PaymentMethodTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Payment\Struct\PaymentMethodTranslationSearchResult;

class PaymentMethodTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'payment_method_translation.search.result.loaded';

    /**
     * @var PaymentMethodTranslationSearchResult
     */
    protected $result;

    public function __construct(PaymentMethodTranslationSearchResult $result)
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
