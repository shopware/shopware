<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

#[Package('core')]
class IndexerOffset
{
    /**
     * @var list<string>
     */
    protected array $definitions;

    /**
     * @var list<string>
     */
    protected array $allDefinitions;

    protected ?string $languageId = null;

    protected ?string $definition = null;

    /**
     * @param list<string> $languages
     * @param iterable<AbstractElasticsearchDefinition> $definitions
     * @param array{offset: int|null}|null $lastId
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

        $this->selectNextLanguage();
        $this->selectNextDefinition();
    }

    /**
     * @deprecated tag:v6.6.0 - Will be removed. Use selectNextDefinition instead
     *
     * @phpstan-ignore-next-line ignore needs to be removed when deprecation is removed
     */
    public function setNextDefinition(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0', 'selectNextDefinition')
        );

        return $this->selectNextDefinition();
    }

    public function selectNextDefinition(): ?string
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

    /**
     * @deprecated tag:v6.6.0 - Will be removed. Use selectNextLanguage instead
     *
     * @phpstan-ignore-next-line ignore needs to be removed when deprecation is removed
     */
    public function setNextLanguage(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0', 'selectNextLanguage')
        );

        return $this->selectNextLanguage();
    }

    public function selectNextLanguage(): ?string
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
     * @return array{offset: int|null}|null
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
     * @param array{offset: int|null}|null $lastId
     */
    public function setLastId(?array $lastId): void
    {
        $this->lastId = $lastId;
    }
}
