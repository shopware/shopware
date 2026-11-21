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
final class Sort extends AbstractDto
{
    public string $field;

    #[Assert\Valid]
    public SortOptions $options;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
