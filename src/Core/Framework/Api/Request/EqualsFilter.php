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
final readonly class EqualsFilter
{
    public function __construct(
        #[Assert\NotBlank]
        public string $field,
        public mixed $value,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['equals'])]
        public string $type = 'equals',
    ) {
    }
}
