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
final class Criteria extends AbstractDto
{
    /**
     * @var array<string, Criteria>
     */
    #[Assert\Valid]
    public array $associations;

    /**
     * @var array<string, list<string>>
     */
    public array $includes;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
