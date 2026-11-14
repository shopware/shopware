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
 * Used for submitting contact forms.
 *
 * @codeCoverageIgnore
 */
final class SendContactMailRequest extends AbstractRequest
{
    /**
     * Identifier of the salutation.
     */
    #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
    public string $salutationId;

    /**
     * @internal
     */
    public function __construct(
        /**
         * Email address
         */
        #[Assert\NotNull]
        public string $email,
        /**
         * The message of the contact form
         */
        #[Assert\NotNull]
        public string $comment,
    ) {
    }
}
