<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Log\Package;

/**
 * The write payload of a clone, split into the insert and the references that can only be written after it.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ClonePayload
{
    /**
     * @param array<string, mixed> $payload the insert payload of the clone's root row
     * @param array<string, list<array<string, string>>> $deferredUpdates update payloads by entity name, written after the insert
     */
    public function __construct(
        public array $payload,
        public array $deferredUpdates = [],
    ) {
    }
}
