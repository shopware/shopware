<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-15 11:21:49
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient;

use Shopware\Core\Framework\Api\Request\StoreApi\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Perform a filtered search for newsletter recipients.
 */
#[Package('checkout')]
final readonly class ReadNewsletterRecipientRequest
{
    public function __construct(
        /**
         * Search parameters. For more information, see our documentation on [Search Queries](https://shopware.stoplight.io/docs/store-api/docs/concepts/search-queries.md#structure)
         */
        #[Assert\Valid]
        public ?Criteria $criteria = null,
    ) {
    }
}
