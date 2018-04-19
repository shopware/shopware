<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Search;

/**
 * The criteria object is used for the search gateway.
 *
 * The sorting, facet and condition classes are defined global and has
 * to be compatible with all gateway engines.
 *
 * Each of this sorting, facet and condition classes are handled by their
 * own handler classes which implemented for each gateway engine.
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Criteria implements \JsonSerializable
{
    /**
     * Offset for the limitation
     *
     * @var int
     */
    private $offset;

    /**
     * Count of result
     *
     * @var int
     */
    private $limit;

    /**
     * @var ConditionInterface[]
     */
    private $baseConditions = [];

    /**
     * @var ConditionInterface[]
     */
    private $conditions = [];

    /**
     * @var FacetInterface[]
     */
    private $facets = [];

    /**
     * @var SortingInterface[]
     */
    private $sortings = [];

    /**
     * @var bool
     */
    private $generatePartialFacets = false;

    /**
     * @var bool
     */
    private $fetchCount = false;

    /**
     * @param int $offset
     *
     * @return $this
     */
    public function offset($offset): Criteria
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @param int $limit
     *
     * @return $this
     */
    public function limit($limit): Criteria
    {
        if ($limit === null) {
            $this->limit = null;

            return $this;
        }

        $this->limit = $limit;

        return $this;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function hasCondition(string $name): bool
    {
        if (array_key_exists($name, $this->baseConditions)) {
            return true;
        }

        return array_key_exists($name, $this->conditions);
    }

    public function hasBaseCondition(string $name): bool
    {
        return array_key_exists($name, $this->baseConditions);
    }

    public function hasUserCondition(string $name): bool
    {
        return array_key_exists($name, $this->conditions);
    }

    public function hasSorting(string $name): bool
    {
        return array_key_exists($name, $this->sortings);
    }

    public function hasFacet(string $name): bool
    {
        return array_key_exists($name, $this->facets);
    }

    public function addFacet(FacetInterface $facet): Criteria
    {
        $this->facets[$facet->getName()] = $facet;

        return $this;
    }

    public function addCondition(ConditionInterface $condition): Criteria
    {
        $this->conditions[$condition->getName()] = $condition;

        return $this;
    }

    public function addBaseCondition(ConditionInterface $condition): Criteria
    {
        $this->baseConditions[$condition->getName()] = $condition;

        return $this;
    }

    public function addSorting(SortingInterface $sorting)
    {
        $this->sortings[$sorting->getName()] = $sorting;

        return $this;
    }

    public function getCondition(string $name): ?ConditionInterface
    {
        if (array_key_exists($name, $this->baseConditions)) {
            return $this->baseConditions[$name];
        }

        if (array_key_exists($name, $this->conditions)) {
            return $this->conditions[$name];
        }

        return null;
    }

    public function getBaseCondition(string $name): ConditionInterface
    {
        return $this->baseConditions[$name];
    }

    public function getUserCondition(string $name): ConditionInterface
    {
        return $this->conditions[$name];
    }

    public function getFacet(string $name): ?FacetInterface
    {
        return $this->facets[$name];
    }

    /**
     * @param string $name
     *
     * @return null|SortingInterface
     */
    public function getSorting(string $name): ?SortingInterface
    {
        return $this->sortings[$name];
    }

    /**
     * Returns all conditions, including the base conditions.
     *
     * Do not rely on the array key or the order of the returned conditions.
     *
     * @return \Shopware\Search\ConditionInterface[]
     */
    public function getConditions(): array
    {
        return array_merge(
            array_values($this->baseConditions),
            array_values($this->conditions)
        );
    }

    /**
     * @return FacetInterface[]
     */
    public function getFacets(): array
    {
        return $this->facets;
    }

    /**
     * @return SortingInterface[]
     */
    public function getSortings(): array
    {
        return $this->sortings;
    }

    public function resetSorting(): Criteria
    {
        $this->sortings = [];

        return $this;
    }

    public function resetBaseConditions(): Criteria
    {
        $this->baseConditions = [];

        return $this;
    }

    public function resetConditions(): Criteria
    {
        $this->conditions = [];

        return $this;
    }

    public function resetFacets(): Criteria
    {
        $this->facets = [];

        return $this;
    }

    public function removeCondition($name): void
    {
        if (array_key_exists($name, $this->conditions)) {
            unset($this->conditions[$name]);
        }
    }

    public function removeBaseCondition($name): void
    {
        if (array_key_exists($name, $this->baseConditions)) {
            unset($this->baseConditions[$name]);
        }
    }

    public function removeFacet($name): void
    {
        if (array_key_exists($name, $this->facets)) {
            unset($this->facets[$name]);
        }
    }

    public function removeSorting($name): void
    {
        if (array_key_exists($name, $this->sortings)) {
            unset($this->sortings[$name]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        $data = get_object_vars($this);

        $data['baseConditions'] = [];
        foreach ($this->baseConditions as $object) {
            $data['baseConditions'][get_class($object)] = $object;
        }

        $data['conditions'] = [];
        foreach ($this->conditions as $object) {
            $data['conditions'][get_class($object)] = $object;
        }

        $data['sortings'] = [];
        foreach ($this->sortings as $object) {
            $data['sortings'][get_class($object)] = $object;
        }

        $data['facets'] = [];
        foreach ($this->facets as $object) {
            $data['facets'][get_class($object)] = $object;
        }

        return $data;
    }

    /**
     * @return ConditionInterface[]
     */
    public function getBaseConditions(): array
    {
        return $this->baseConditions;
    }

    public function generatePartialFacets(): bool
    {
        return $this->generatePartialFacets;
    }

    public function setGeneratePartialFacets($generatePartialFacets): void
    {
        $this->generatePartialFacets = $generatePartialFacets;
    }

    /**
     * @return ConditionInterface[]
     */
    public function getUserConditions(): array
    {
        return $this->conditions;
    }

    public function fetchCount(): bool
    {
        return $this->fetchCount;
    }

    public function setFetchCount(bool $fetchCount)
    {
        $this->fetchCount = $fetchCount;

        return $this;
    }
}
