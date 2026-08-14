<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class DefaultValues extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $query,
        public int $limit = 10,
        public string $sortOrder = 'relevance',
        public bool $active = true,
        /**
         * Single-value enum, should default to the only value
         */
        #[Assert\Choice(choices: ['storefront'])]
        public string $source = 'storefront',
        /**
         * Multi-value enum with explicit default
         */
        #[Assert\Choice(choices: ['web', 'api'])]
        public string $channel = 'web',
    ) {
    }
}
