<?php declare(strict_types=1);
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

namespace Shopware\CartBridge\Rule\Collector;

use Doctrine\DBAL\Connection;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\Cart\CollectorInterface;
use Shopware\CartBridge\Product\Struct\ProductFetchDefinition;
use Shopware\Cart\Rule\RuleCollection;
use Shopware\Cart\Rule\Validatable;
use Shopware\CartBridge\Rule\Data\ProductAttributeRuleData;
use Shopware\CartBridge\Rule\ProductAttributeRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class ProductAttributeRuleCollector implements CollectorInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function prepare(
        StructCollection $fetchDefinition,
        CartContainer $cartContainer,
        ShopContext $context
    ): void {
    }

    public function fetch(
        StructCollection $dataCollection,
        StructCollection $fetchCollection,
        ShopContext $context
    ): void {
        $rules = $dataCollection->filterInstance(Validatable::class);

        $rules = $rules->map(function (Validatable $validatable) {
            return $validatable->getRule();
        });

        $rules = new RuleCollection($rules);
        if (!$rules->has(ProductAttributeRule::class)) {
            return;
        }

        $numbers = $this->getNumbers($fetchCollection);

        if (empty($numbers)) {
            return;
        }

        $data = $this->fetchAttributes($numbers);

        if (empty($data)) {
            return;
        }

        $keys = array_keys($data[0]);

        $values = [];
        foreach ($keys as $attribute) {
            $values[$attribute] = array_column($data, $attribute);
        }

        $dataCollection->add(
            new ProductAttributeRuleData($values),
            ProductAttributeRuleData::class
        );
    }

    private function getNumbers(StructCollection $fetchDefinition): array
    {
        $definitions = $fetchDefinition->filterInstance(ProductFetchDefinition::class);
        if ($definitions->count() === 0) {
            return [];
        }

        $numbers = [];

        /** @var ProductFetchDefinition $definition */
        foreach ($definitions as $definition) {
            $numbers = array_merge($numbers, $definition->getNumbers());
        }

        //fast array unique
        return array_keys(array_flip($numbers));
    }

    private function fetchAttributes(array $numbers): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['attribute.*']);
        $query->from('s_articles_attributes', 'attribute');
        $query->innerJoin('attribute', 's_articles_details', 'variant', 'variant.id = attribute.articledetailsID');
        $query->where('variant.ordernumber IN (:numbers)');
        $query->setParameter('numbers', $numbers, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
}
