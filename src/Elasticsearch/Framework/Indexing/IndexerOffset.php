<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Log\Package;

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

    protected ?string $definition = null;

    /**
     * @param iterable<string> $mappingDefinitions
     * @param array{offset: int|null}|null $lastId
     */
    public function __construct(
        iterable $mappingDefinitions,
        protected ?int $timestamp,
        protected ?array $lastId = null
    ) {
        $mapping = [];
        /** @var string $mappingDefinition */
        foreach ($mappingDefinitions as $mappingDefinition) {
            $mapping[] = $mappingDefinition;
        }

        $this->allDefinitions = $mapping;
        $this->definitions = $mapping;

        $this->selectNextDefinition();
    }

    public function selectNextDefinition(): void
    {
        $this->definition = array_shift($this->definitions);
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

    /**
     * @internal This method is internal and will be used by Symfony serializer
     *
     * @return array<string>
     */
    public function getAllDefinitions(): array
    {
        return $this->allDefinitions;
    }

    /**
     * @param list<string> $allDefinitions
     *
     * @internal This method is internal and will be used by Symfony serializer
     */
    public function setAllDefinitions(array $allDefinitions): void
    {
        $this->allDefinitions = $allDefinitions;
    }

    /**
     * @param list<string> $definitions
     *
     * @internal This method is internal and will be used by Symfony serializer
     */
    public function setDefinitions(array $definitions): void
    {
        $this->definitions = $definitions;
    }

    /**
     * @internal This method is internal and will be used by Symfony serializer
     */
    public function setDefinition(?string $definition): void
    {
        $this->definition = $definition;
    }
}
