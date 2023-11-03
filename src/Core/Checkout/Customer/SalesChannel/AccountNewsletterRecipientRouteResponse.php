<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('checkout')]
class AccountNewsletterRecipientRouteResponse extends StoreApiResponse
{
    /**
     * @var AccountNewsletterRecipientResult
     */
    protected $object;

    /**
     * @param EntitySearchResult<NewsletterRecipientCollection> $newsletterRecipients
     */
    public function __construct(EntitySearchResult $newsletterRecipients)
    {
        $firstNewsletterRecipient = $newsletterRecipients->getEntities()->first();
        if ($firstNewsletterRecipient) {
            $accNlRecipientResult = new AccountNewsletterRecipientResult($firstNewsletterRecipient->getStatus());
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
