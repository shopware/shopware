<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Read newsletter recipients.
 *
 * @codeCoverageIgnore
 */
final class ReadNewsletterRecipientRequest extends AbstractRequest
{
    #[Assert\Valid]
    public Criteria $criteria;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
