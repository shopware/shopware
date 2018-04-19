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

namespace Shopware\Product\Writer\Api;

class WritableField extends Field
{
    /**
     * @var array
     */
    private $insertConstraints;

    /**
     * @var array
     */
    private $updateConstraints;

    /**
     * @var string
     */
    private $storageName;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @param string $name
     * @param string $storageName
     * @param string $tableName
     * @param array  $insertConstraints
     * @param array  $updateConstraints
     */
    public function __construct(string $name, string $storageName, string $tableName, array $insertConstraints = [], array $updateConstraints = [])
    {
        parent::__construct($name);
        $this->tableName = $tableName;
        $this->storageName = $storageName;
        $this->insertConstraints = $insertConstraints;
        $this->updateConstraints = $updateConstraints;
    }

    /**
     * @return array
     */
    public function getInsertConstraints(): array
    {
        return $this->insertConstraints;
    }

    /**
     * @return array
     */
    public function getUpdateConstraints(): array
    {
        return $this->updateConstraints;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return [];
    }

    /**
     * @return ValueTransformerNoOp
     */
    public function getValueTransformer()
    {
        return new ValueTransformerNoOp();
    }

    /**
     * @return string
     */
    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }
}
