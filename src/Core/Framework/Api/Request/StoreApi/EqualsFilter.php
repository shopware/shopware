<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-10-15 12:34:03
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class EqualsFilter extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Choice(choices: ['equals'])]
        public string $type,
        #[Assert\NotNull]
        public string $field,
        public string|float|bool|null $value,
    ) {
    }
}
