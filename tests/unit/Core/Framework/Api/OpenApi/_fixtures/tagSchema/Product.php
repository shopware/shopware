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
 * @codeCoverageIgnore
 */
final class Product extends AbstractRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        #[Assert\NotBlank]
        public string $name,
        #[Assert\Valid]
        public ?Media $cover = null,
    ) {
    }
}
