<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 11:51:52
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

#[Package('checkout')]
final readonly class AccountNewsletterRecipient
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Valid]
        public NewsletterStatus $status,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['account_newsletter_recipient'])]
        public string $apiAlias = 'account_newsletter_recipient',
    ) {
    }
}
