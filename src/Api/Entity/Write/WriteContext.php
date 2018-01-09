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

namespace Shopware\Api\Entity\Write;

use Shopware\Api\Shop\Definition\ShopDefinition;
use Shopware\Context\Struct\TranslationContext;

class WriteContext
{
    private const SPACER = '::';
    private $paths = [];

    /**
     * @var array[]
     */
    private $pkMapping = [];

    /**
     * @var array[]
     */
    private $primaryKeys = [];

    /**
     * @var TranslationContext
     */
    private $translationContext;

    private function __construct(TranslationContext $translationContext)
    {
        $this->translationContext = $translationContext;
    }

    public static function createFromTranslationContext(TranslationContext $context): self
    {
        $self = new self($context);
        $self->set(ShopDefinition::class, 'id', $context->getShopId());

        return $self;
    }

    /**
     * @param string $className
     * @param string $propertyName
     * @param string $value
     */
    public function set(string $className, string $propertyName, string $value): void
    {
        $this->paths[$this->buildPathName($className, $propertyName)] = $value;
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return mixed
     */
    public function get(string $className, string $propertyName)
    {
        $path = $this->buildPathName($className, $propertyName);

        if (!$this->has($className, $propertyName)) {
            throw new \InvalidArgumentException(sprintf('Unable to load %s: %s', $path, print_r($this->paths, true)));
        }

        return $this->paths[$path];
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return bool
     */
    public function has(string $className, string $propertyName): bool
    {
        $path = $this->buildPathName($className, $propertyName);

        return isset($this->paths[$path]);
    }

    /**
     * @param string $tableName
     * @param array  $primaryKeys
     */
    public function addPrimaryKeyMapping(string $tableName, array $primaryKeys)
    {
        if (!array_key_exists($tableName, $this->pkMapping)) {
            $this->pkMapping[$tableName] = ['rows' => [], 'columns' => []];
        }

        $this->pkMapping[$tableName]['rows'][] = $primaryKeys;
        $this->pkMapping[$tableName]['columns'] += array_flip(array_keys($primaryKeys));
    }

    /**
     * @return array
     */
    public function getPrimaryKeyMapping(): array
    {
        return $this->pkMapping;
    }

    /**
     * @param string $tableName
     *
     * @return array
     */
    public function getPrimaryKeysForTable(string $tableName): array
    {
        return $this->pkMapping[$tableName];
    }

    /**
     * @param string $table
     * @param array  $existingPrimaries
     */
    public function setExistingPrimaries(string $table, array $existingPrimaries)
    {
        foreach ($existingPrimaries as $row) {
            ksort($row);
            $this->primaryKeys[$table][] = $row;
        }
    }

    /**
     * @param string $table
     * @param array  $primaryKey
     *
     * @return bool
     */
    public function primaryKeyExists(string $table, array $primaryKey): bool
    {
        if (!array_key_exists($table, $this->primaryKeys)) {
            return false;
        }

        $keys = $this->primaryKeys[$table];
        ksort($primaryKey);
        foreach ($keys as $key) {
            if ($key === $primaryKey) {
                return true;
            }
        }
        return false;
    }

    public function getTranslationContext(): TranslationContext
    {
        return $this->translationContext;
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return string
     */
    private function buildPathName(string $className, string $propertyName): string
    {
        return $className . self::SPACER . $propertyName;
    }
}
