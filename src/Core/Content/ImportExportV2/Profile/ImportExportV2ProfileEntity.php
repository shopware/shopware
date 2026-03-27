<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Profile;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportV2ProfileEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $entity;

    protected string $format;

    /**
     * @var list<string>
     */
    protected array $identifierPaths = [];

    /**
     * Paths that may be written or exported for this profile.
     *
     * @var list<string>
     */
    protected array $payloadPaths = [];

    /**
     * @var array<string, string>
     */
    protected array $relationModes = [];

    /**
     * Used by flat formats such as CSV to map columns to the shared record shape.
     *
     * @var list<array<string, mixed>>
     */
    protected array $fieldMappings = [];

    public function getFormatFileName(string $formatName): string
    {
        return $this->name . '.' . $formatName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): void
    {
        $this->format = $format;
    }

    /**
     * @return list<string>
     */
    public function getIdentifierPaths(): array
    {
        return $this->identifierPaths;
    }

    /**
     * @param list<string> $identifierPaths
     */
    public function setIdentifierPaths(array $identifierPaths): void
    {
        $this->identifierPaths = $identifierPaths;
    }

    /**
     * @return list<string>
     */
    public function getPayloadPaths(): array
    {
        return $this->payloadPaths;
    }

    /**
     * @param list<string> $payloadPaths
     */
    public function setPayloadPaths(array $payloadPaths): void
    {
        $this->payloadPaths = $payloadPaths;
    }

    /**
     * @return array<string, string>
     */
    public function getRelationModes(): array
    {
        return $this->relationModes;
    }

    /**
     * @param array<string, string> $relationModes
     */
    public function setRelationModes(array $relationModes): void
    {
        $this->relationModes = $relationModes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFieldMappings(): array
    {
        return $this->fieldMappings;
    }

    /**
     * @param list<array<string, mixed>> $fieldMappings
     */
    public function setFieldMappings(array $fieldMappings): void
    {
        $this->fieldMappings = $fieldMappings;
    }
}
