<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-10-29 15:45:47
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Perform a filtered search for newsletter recipients.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class ReadNewsletterRecipientStatusResponse extends AbstractResponse
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public NewsletterStatus $status,
    ) {
        parent::__construct();
    }
}
