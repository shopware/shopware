<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-15 11:25:19
 */

namespace Shopware\Core\Framework\Api\Request\AdminApi;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

#[Package('framework')]
final readonly class MultiNotFilter
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['multi', 'not'])]
        public string $type,
        /**
         * @var list<array|EqualsFilter|MultiNotFilter|RangeFilter>
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public array $queries,
        #[Assert\Choice(choices: ['and', 'or', 'nor', 'nand'])]
        public ?string $operator = null,
    ) {
    }
}
