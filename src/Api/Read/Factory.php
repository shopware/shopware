<?php declare(strict_types=1);

namespace Shopware\Api\Read;

use Doctrine\DBAL\Connection;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;

abstract class Factory
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var ExtensionRegistryInterface
     */
    protected $registry;

    public function __construct(Connection $connection, ExtensionRegistryInterface $registry)
    {
        $this->connection = $connection;
        $this->registry = $registry;
    }

    public function getFields(): array
    {
        return $this->getExtensionFields();
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function createQuery(TranslationContext $translationContext): QueryBuilder
    {
        $select = QuerySelection::createFromNestedFields($this->getFields(), $this->getRootName());

        return $this->createQueryFromContext($select, $translationContext);
    }

    public function createQueryFromContext(QuerySelection $selection, TranslationContext $translationContext): QueryBuilder
    {
        $query = new QueryBuilder($this->connection, $selection);
        $query->addSelect($selection->buildSelect());
        $query->from(QuerySelection::escape($this->getRootName()), $selection->getRootEscaped());

        $this->joinDependencies($selection, $query, $translationContext);

        return $query;
    }

    public function createSearchQuery(Criteria $criteria, TranslationContext $translationContext): QueryBuilder
    {
        $mapping = QuerySelection::createFromNestedFields($this->getAllFields(), $this->getRootName());

        $selection = QuerySelection::createFromCriteria(
            $criteria,
            $this->getRootName(),
            $mapping->getFields()
        );

        $query = new QueryBuilder($this->connection, $selection);
        $query->select(QuerySelection::escapeFieldSelect($this->getRootName() . '.uuid'));
        $query->from(QuerySelection::escape($this->getRootName()), $selection->getRootEscaped());

        $this->joinDependencies($selection, $query, $translationContext);

        return $query;
    }

    public function getAllFields(): array
    {
        return $this->getFields();
    }

    abstract protected function getRootName(): string;

    abstract protected function getExtensionNamespace(): string;

    protected function joinExtensionDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        foreach ($this->getExtensions() as $extension) {
            $extension->joinDependencies($selection, $query, $context);
        }
    }

    /**
     * @return FactoryExtensionInterface[]
     */
    protected function getExtensions(): array
    {
        return $this->registry->getExtensions($this->getExtensionNamespace());
    }

    protected function getExtensionFields(): array
    {
        $fields = [];
        foreach ($this->getExtensions() as $extension) {
            $extensionFields = $extension->getBasicFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
