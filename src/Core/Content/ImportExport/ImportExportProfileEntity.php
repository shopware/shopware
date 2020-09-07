<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ImportExportProfileEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var bool
     */
    protected $systemDefault;

    /**
     * @var string
     */
    protected $sourceEntity;

    /**
     * @var string
     */
    protected $fileType;

    /**
     * @var string|null
     */
    protected $delimiter;

    /**
     * @var string|null
     */
    protected $enclosure;

    /**
     * @var array|null
     */
    protected $mapping;

    /**
     * @var ImportExportLogCollection|null
     */
    protected $importExportLogs;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var ImportExportProfileTranslationCollection|null
     */
    protected $translations;

    /**
     * @var \DateTimeInterface
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getMapping(): ?array
    {
        return $this->mapping;
    }

    public function setMapping(array $mapping): void
    {
        $this->mapping = $mapping;
    }

    public function getImportExportLogs(): ?ImportExportLogCollection
    {
        return $this->importExportLogs;
    }

    public function setImportExportLogs(ImportExportLogCollection $importExportLogs): void
    {
        $this->importExportLogs = $importExportLogs;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

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
}
