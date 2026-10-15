<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class BusinessInput extends AbstractRequest
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $sharedValue,
        #[Assert\NotBlank]
        public string $siblingShared,
        #[Assert\NotBlank]
        public string $businessValue,
        #[Assert\NotBlank]
        public string $businessSibling,
    ) {
    }
}
