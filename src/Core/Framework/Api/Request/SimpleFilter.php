<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 11:37:53
 */

namespace Shopware\Core\Framework\Api\Request;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

#[Package('framework')]
final readonly class SimpleFilter
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['contains', 'equalsAny', 'prefix', 'suffix'])]
        public string $type,
        #[Assert\NotBlank]
        public string $field,
        #[Assert\NotBlank]
        public string $value,
    ) {
    }
}
