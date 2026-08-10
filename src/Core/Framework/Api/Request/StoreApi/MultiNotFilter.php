<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-10 15:01:45
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class MultiNotFilter
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['multi', 'not'])]
        public string $type,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['and', 'or', 'nor', 'nand'])]
        public string $operator,
        /**
         * @var list<SimpleFilter|EqualsFilter|MultiNotFilter|RangeFilter>
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public array $queries,
    ) {
    }
}
