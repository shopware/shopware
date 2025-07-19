<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportProfileEntity extends Entity
{
    use EntityIdTrait;

    final public const TYPE_IMPORT = 'import';
    final public const TYPE_EXPORT = 'export';
    final public const TYPE_IMPORT_EXPORT = 'import-export';

    protected string $technicalName;

    protected string $label;

    protected bool $systemDefault;

    protected string $sourceEntity;

    protected string $fileType;

    protected ?string $delimiter = null;

    protected ?string $enclosure = null;

    protected string $type;

    /**
     * @var list<array{key: string, mappedKey: string}>|array<Mapping>|null
     */
    protected ?array $mapping = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $updateBy = [];

    protected ?ImportExportLogCollection $importExportLogs = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    protected ?ImportExportProfileTranslationCollection $translations = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getSystemDefault(): bool
    {
        return $this->systemDefault;
    }

    public function setSystemDefault(bool $systemDefault): void
    {
        $this->systemDefault = $systemDefault;
    }

    public function getSourceEntity(): string
    {
        return $this->sourceEntity;
    }

    public function setSourceEntity(string $sourceEntity): void
    {
        $this->sourceEntity = $sourceEntity;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }

    public function setFileType(string $fileType): void
    {
        $this->fileType = $fileType;
    }

    public function getDelimiter(): ?string
    {
        return $this->delimiter;
    }

    public function setDelimiter(string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    public function getEnclosure(): ?string
    {
        return $this->enclosure;
    }

    public function setEnclosure(string $enclosure): void
    {
        $this->enclosure = $enclosure;
    }

    /**
     * @return list<array{key: string, mappedKey: string}>|array<Mapping>|null
     */
    public function getMapping(): ?array
    {
        return $this->mapping;
    }

    /**
     * @param list<array{key: string, mappedKey: string}>|array<Mapping> $mapping
     */
    public function setMapping(array $mapping): void
    {
        $this->mapping = $mapping;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUpdateBy(): ?array
    {
        return $this->updateBy;
    }

    /**
     * @param array<string, mixed>|null $updateBy
     */
    public function setUpdateBy(?array $updateBy): void
    {
        $this->updateBy = $updateBy;
    }

    public function getImportExportLogs(): ?ImportExportLogCollection
    {
        return $this->importExportLogs;
    }

    public function setImportExportLogs(ImportExportLogCollection $importExportLogs): void
    {
        $this->importExportLogs = $importExportLogs;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getTranslations(): ?ImportExportProfileTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ImportExportProfileTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
