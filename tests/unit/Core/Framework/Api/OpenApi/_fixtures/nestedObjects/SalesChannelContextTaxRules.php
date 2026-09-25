<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
final class SalesChannelContextTaxRules extends AbstractDto
{
    public string $name;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public float $taxRate,
    ) {
    }
}
