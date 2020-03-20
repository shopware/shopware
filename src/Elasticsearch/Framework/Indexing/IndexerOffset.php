<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

class IndexerOffset
{
    /**
     * @var string[]
     */
    protected $languages;

    /**
     * @var string[]
     */
    protected $definitions;

    /**
     * @var string[]
     */
    protected $allDefinitions;

    /**
     * @var int|null
     */
    protected $timestamp;

    /**
     * @var array|null
     */
    protected $lastId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $definition;

    public function __construct(
        LanguageCollection $languages,
        iterable $definitions,
        ?int $timestamp,
        $lastId = null,
        ?string $definition = null,
        ?string $languageId = null
    ) {
        $this->languages = array_values($languages->getIds());

        $mapping = [];
        /** @var AbstractElasticsearchDefinition $loop */
        foreach ($definitions as $loop) {
            $mapping[] = $loop->getEntityDefinition()->getEntityName();
        }

        $this->allDefinitions = $mapping;

        if (!$languageId) {
            $languageId = array_shift($this->languages);
        }
        if (!$definition) {
            $definition = array_shift($mapping);
        }

        $this->definitions = $mapping;
        $this->timestamp = $timestamp;
        $this->definition = $definition;

        $this->lastId = $lastId;
        $this->languageId = $languageId;
    }

    public function setNextDefinition(): ?string
    {
        return $this->definition = array_shift($this->definitions);
    }

    public function resetDefinitions(): void
    {
        $this->definitions = $this->allDefinitions;
        $this->definition = array_shift($this->definitions);
    }

    public function hasNextDefinition(): bool
    {
        return !empty($this->definitions);
    }

    public function setNextLanguage(): string
    {
        return $this->languageId = array_shift($this->languages);
    }

    public function hasNextLanguage(): bool
    {
        return !empty($this->languages);
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getLanguages(): array
    {
        return $this->languages;
    }

    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    public function getLastId()
    {
        return $this->lastId;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function setLastId(?array $lastId): void
    {
        $this->lastId = $lastId;
    }
}
