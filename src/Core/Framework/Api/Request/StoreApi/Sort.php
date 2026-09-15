<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-14 11:56:12
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[JsonStreamable]
final class Sort extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $field,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['ASC', 'DESC'])]
        public string $order,
        public ?bool $naturalSorting = null,
        public ?string $type = null,
    ) {
    }
}
