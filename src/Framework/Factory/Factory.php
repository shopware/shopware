<?php

namespace Shopware\Framework\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

abstract class Factory
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var FactoryExtensionInterface[]
     */
    protected $extensions = [];

    public function __construct(Connection $connection, array $extensions)
    {
        $this->connection = $connection;
        $this->extensions = $extensions;
    }

    abstract protected function getRootName(): string;

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
        $query->from($this->getRootName(), $selection->getRootEscaped());

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
        $query->from($this->getRootName(), $selection->getRootEscaped());

        $this->joinDependencies($selection, $query, $translationContext);

        return $query;
    }

    protected function joinExtensionDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        foreach ($this->extensions as $extension) {
            $extension->joinDependencies($selection, $query, $context);
        }
    }

    protected function getExtensionFields(): array
    {
        $fields = [];
        foreach ($this->extensions as $extension) {
            $extensionFields = $extension->getBasicFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }
        return $fields;
    }

    public function getAllFields(): array
    {
        return $this->getFields();
    }
}