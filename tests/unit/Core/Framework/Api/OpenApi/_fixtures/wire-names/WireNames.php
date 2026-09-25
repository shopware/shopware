<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
final class WireNames extends AbstractDto
{
    #[SerializedName('post-filter')]
    public string $postFilter;

    public string $camelCase;

    #[SerializedName('snake_case')]
    public string $snakeCase;

    #[SerializedName('quote\'field')]
    public string $quoteField;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        #[SerializedName('total-count-mode')]
        public int $totalCountMode,
    ) {
    }
}
