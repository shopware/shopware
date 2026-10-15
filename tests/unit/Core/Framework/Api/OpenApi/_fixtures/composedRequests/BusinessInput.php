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
        #[Assert\NotNull]
        public string $sharedValue,
        #[Assert\NotNull]
        public string $siblingShared,
        #[Assert\NotNull]
        public string $businessValue,
        #[Assert\NotNull]
        public string $businessSibling,
    ) {
    }
}
