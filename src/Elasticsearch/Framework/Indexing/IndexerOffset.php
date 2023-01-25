<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

/**
 * @phpstan-import-type Offset from IterableQuery
 */
#[Package('core')]
class IndexerOffset
{
    /**
     * @var array<string>
     */
    protected array $definitions;

    /**
     * @var array<string>
     */
    protected array $allDefinitions;

    protected ?string $languageId = null;

    protected ?string $definition = null;

    /**
     * @param list<string> $languages
     * @param iterable<AbstractElasticsearchDefinition> $definitions
     * @param Offset|null $lastId
     */
    public function __construct(
        protected array $languages,
        iterable $definitions,
        protected ?int $timestamp,
        protected ?array $lastId = null
    ) {
        $mapping = [];
        /** @var AbstractElasticsearchDefinition $definition */
        foreach ($definitions as $definition) {
            $mapping[] = $definition->getEntityDefinition()->getEntityName();
        }

        $this->allDefinitions = $mapping;
        $this->definitions = $mapping;

        $this->setNextLanguage();
        $this->setNextDefinition();
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

    public function setNextLanguage(): ?string
    {
        return $this->languageId = array_shift($this->languages);
    }

    public function hasNextLanguage(): bool
    {
        return !empty($this->languages);
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    /**
     * @return list<string>
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @return list<string>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    /**
     * @return Offset|null
     */
    public function getLastId(): ?array
    {
        return $this->lastId;
    }

    public function getDefinition(): ?string
    {
        return $this->definition;
    }

    /**
     * @param Offset|null $lastId
     */
    public function setLastId(?array $lastId): void
    {
        $this->lastId = $lastId;
    }
}
