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
final readonly class RangeFilter
{
    public function __construct(
        #[Assert\NotBlank]
        public string $field,
        #[Assert\NotNull]
        #[Assert\Valid]
        public RangeFilterParameters $parameters,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['range'])]
        public string $type = 'range',
    ) {
    }
}
