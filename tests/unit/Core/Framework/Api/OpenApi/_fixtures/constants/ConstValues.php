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
final class ConstValues extends AbstractDto
{
    #[Assert\IdenticalTo(value: 'fixed')]
    public string $optionalKind;

    #[Assert\IdenticalTo(value: false)]
    public bool $enabled;

    #[Assert\IdenticalTo(value: 0)]
    public int $count;

    #[Assert\IdenticalTo(value: 1.0)]
    public float $ratio;

    #[Assert\IdenticalTo(value: 'it\'s\\fixed')]
    public string $quoted;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\IdenticalTo(value: 'fixed')]
        public string $kind,
    ) {
    }
}
