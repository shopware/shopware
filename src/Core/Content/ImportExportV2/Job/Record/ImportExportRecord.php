<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Record;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final readonly class ImportExportRecord implements \JsonSerializable
{
    /**
     * This is the shared record shape inside ImportExportV2.
     * Format readers translate JSON or CSV into this structure, and the DAL mappers
     * translate this structure to and from Shopware entities.
     *
     * @param array<string, mixed> $identifier
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private string $entity,
        private array $identifier,
        private array $payload
    ) {
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function getIdentifier(): array
    {
        return $this->identifier;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return array{entity: string, identifier: array<string, mixed>, payload: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'entity' => $this->entity,
            'identifier' => $this->identifier,
            'payload' => $this->payload,
        ];
    }
}
