<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-13 12:40:10
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[JsonStreamable]
final class EqualsFilter extends AbstractRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $field,
        public string|float|bool|null $value,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['equals'])]
        public string $type = 'equals',
    ) {
    }
}
