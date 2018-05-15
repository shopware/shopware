<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Event\PaymentMethodTranslation;

use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class PaymentMethodTranslationIdSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method_translation.id.search.result.loaded';

    /**
     * @var IdSearchResult
     */
    protected $result;

    public function __construct(IdSearchResult $result)
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

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
