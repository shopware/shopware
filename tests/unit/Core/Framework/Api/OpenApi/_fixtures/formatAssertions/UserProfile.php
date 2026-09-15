<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * User profile with various formatted fields
 *
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class UserProfile extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Unique identifier
         */
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $id,
        /**
         * Email address
         */
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        /**
         * Creation timestamp
         */
        #[Assert\NotBlank]
        #[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]
        public string $createdAt,
        #[Assert\Url]
        public ?string $website = null,
        #[Assert\Date]
        public ?string $birthday = null,
        /**
         * Should not produce a format assert
         */
        public ?string $avatar = null,
        public ?int $fileSize = null,
        public ?float $price = null,
        /**
         * Plain string without format
         */
        public ?string $name = null,
    ) {
    }
}
