<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-10-28 00:00:00
 */

namespace Shopware\Tests\Integration\Core\Framework\Api\OpenApi\Fixture;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class FieldDetails extends AbstractDto
{
    public const DEFAULT_NOTE = 'nested-default';

    public ?string $note;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Length(min: 1)]
        public string $name,
    ) {
    }
}
