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
final class CreateSingleRequest extends AbstractRequest
{
    public string $optionalValue;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $baseValue,
        #[Assert\NotBlank]
        public string $siblingValue,
    ) {
    }
}
