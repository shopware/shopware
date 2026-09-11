<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Used for submitting contact forms.
 *
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class SendContactMailRequest extends AbstractRequest
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Email address
         */
        #[Assert\NotBlank]
        public string $email,
        /**
         * The subject of the contact form.
         */
        #[Assert\NotBlank]
        public string $subject,
        /**
         * The message of the contact form
         */
        #[Assert\NotBlank]
        public string $comment,
        /**
         * Identifier of the salutation.
         */
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public ?string $salutationId = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
    ) {
    }
}
