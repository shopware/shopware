<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Profile;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportV2ProfileEntity extends Entity
{
    use EntityIdTrait;

    /**
     * Stable internal profile identifier used to start and load runs.
     */
    protected string $technicalName;

    /**
     * Root DAL entity name, for example `product`.
     */
    protected string $entity;

    /**
     * Selected file format, for example `json` or `csv`.
     */
    protected string $format;

    /**
     * Default DAL filters for this profile.
     *
     * The idea is that a profile should fully describe "what kind of export is
     * this?" That includes not only the record shape, but also which subset of
     * entities should be exported by default.
     *
     * Example:
     * ```php
     * [
     *     [
     *         'type' => 'equals',
     *         'field' => 'active',
     *         'value' => true,
     *     ],
     * ]
     * ```
     *
     * Meaning:
     * - only export active products
     *
     * These filters are still copied onto the run when the export starts, so a
     * long-running export keeps using the same filter payload even if the
     * profile is changed later.
     *
     * @var list<array<string, mixed>>
     */
    protected array $filters = [];

    /**
     * Shared record paths that belong to this profile.
     *
     * A record path describes which values are part of the import/export record,
     * independent of file format. This is the neutral profile-level list that
     * both directions can use:
     * - export uses it to decide which values should be projected from DAL
     * - import can later use it to decide which values are allowed in a record
     * - CSV mappings point to these same paths when flattening columns
     *
     * These paths describe the shape of the record payload, not the database
     * query itself.
     *
     * Example:
     * ```php
     * [
     *     'productNumber',
     *     'active',
     *     'stock',
     *     'tax.id',
     *     'translations.DEFAULT.name',
     *     'categories.*.id',
     * ]
     * ```
     *
     * Meaning:
     * - `productNumber`: include the product number in the record
     * - `active`: include the active flag
     * - `stock`: include the stock value
     * - `tax.id`: include the tax relation as a nested id
     * - `translations.DEFAULT.name`: include the default translated name
     * - `categories.*.id`: include all category ids as a nested list
     *
     * Example record payload that matches the paths above:
     * ```php
     * [
     *     'productNumber' => 'SW10001',
     *     'active' => true,
     *     'stock' => 15,
     *     'tax' => ['id' => 'tax-123'],
     *     'translations' => [
     *         'DEFAULT' => ['name' => 'Demo product'],
     *     ],
     *     'categories' => [
     *         ['id' => 'cat-1'],
     *         ['id' => 'cat-2'],
     *     ],
     * ]
     * ```
     *
     * @var list<string>
     */
    protected array $recordPaths = [];

    /**
     * One root string field used to find an existing root entity during import.
     *
     * `matchBy` tells the import flow how to look up one existing root entity
     * before building the final DAL write payload. If a match is found, the
     * import can inject that root `id` and turn the write into an update
     * instead of creating a duplicate.
     *
     * Current scope:
     * - one field only
     * - root string field only
     * - no dotted paths
     * - no wildcard association paths
     *
     * Example:
     * ```php
     * 'productNumber'
     * ```
     *
     * Meaning:
     * - before importing a product record, look for an existing product where
     *   `productNumber` matches
     *
     * Example record payload:
     * ```php
     * [
     *     'productNumber' => 'SW10001',
     *     'stock' => 15,
     * ]
     * ```
     *
     * Import intent:
     * - if product `SW10001` already exists, update it
     * - if it does not exist, create it
     */
    protected ?string $matchBy = null;

    /**
     * CSV-specific column mappings.
     *
     * JSON export does not need them, but CSV export uses them to decide which
     * columns exist and which record path each column reads from.
     *
     * Example:
     * ```php
     * [
     *     [
     *         'column' => 'product_number',
     *         'path' => 'productNumber',
     *     ],
     *     [
     *         'column' => 'tax_id',
     *         'path' => 'tax.id',
     *     ],
     *     [
     *         'column' => 'category_ids',
     *         'path' => 'categories.*.id',
     *         'separator' => '|',
     *     ],
     * ]
     * ```
     *
     * @var list<array<string, mixed>>
     */
    protected array $fieldMappings = [];

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
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
     * @return list<array<string, mixed>>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @param list<array<string, mixed>> $filters
     */
    public function setFilters(array $filters): void
    {
        $this->filters = $filters;
    }

    /**
     * @return list<string>
     */
    public function getRecordPaths(): array
    {
        return $this->recordPaths;
    }

    /**
     * @param list<string> $recordPaths
     */
    public function setRecordPaths(array $recordPaths): void
    {
        $this->recordPaths = $recordPaths;
    }

    public function getMatchBy(): ?string
    {
        return $this->matchBy;
    }

    /**
     * Use `null` when the profile should not try to match existing root
     * entities during import.
     */
    public function setMatchBy(?string $matchBy): void
    {
        $this->matchBy = $matchBy;
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
