<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-10 11:16:52
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient;

use Shopware\Core\Framework\Api\Response\StoreApi\StoreApiDTOResponseInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Perform a filtered search for newsletter recipients.
 */
#[Package('checkout')]
final readonly class ReadNewsletterRecipientResponse implements StoreApiDTOResponseInterface
{
    public function __construct(
        /**
         * The subscription status. Possible values are: notSet, optIn, optOut, direct, undefined.
         */
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['notSet', 'optIn', 'optOut', 'direct', 'undefined'])]
        public string $status,
        #[Assert\NotBlank]
        public string $apiAlias = 'account_newsletter_recipient',
    ) {
    }
}
