<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 15:09:15
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient;

use Shopware\Core\Framework\Api\Request\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Perform a filtered search for newsletter recipients.
 */
#[Package('checkout')]
final readonly class ReadNewsletterRecipientRequest
{
    public function __construct(
        #[Assert\Valid]
        public ?Criteria $criteria = null,
    ) {
    }
}
