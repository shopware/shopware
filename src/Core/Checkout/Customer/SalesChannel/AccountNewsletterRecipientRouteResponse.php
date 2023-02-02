<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @internal (flag:FEATURE_NEXT_14001) remove this comment on feature release
 */
class AccountNewsletterRecipientRouteResponse extends StoreApiResponse
{
    /**
     * @var AccountNewsletterRecipientResult
     */
    protected $object;

    public function __construct(EntitySearchResult $newsletterRecipients)
    {
        if ($newsletterRecipients->first()) {
            $accNlRecipientResult = new AccountNewsletterRecipientResult($newsletterRecipients->first()->getStatus());
            parent::__construct($accNlRecipientResult);

            return;
        }
        $accNlRecipientResult = new AccountNewsletterRecipientResult();
        parent::__construct($accNlRecipientResult);
    }

    public function getAccountNewsletterRecipient(): AccountNewsletterRecipientResult
    {
        return $this->object;
    }
}
