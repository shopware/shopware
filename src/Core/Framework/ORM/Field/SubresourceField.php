<?php
declare(strict_types=1);
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

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldException\MalformatDataException;

class SubresourceField extends Field
{
    /**
     * @var string|EntityDefinition
     */
    protected $referenceClass;

    /**
     * @var
     */
    protected $possibleKey;

    public function __construct(string $propertyName, string $referenceClass, $possibleKey = null)
    {
        $this->referenceClass = $referenceClass;
        $this->possibleKey = $possibleKey;
        parent::__construct($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    public function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!is_array($value)) {
            throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
        }

        $isNumeric = array_keys($value) === range(0, count($value) - 1);

        foreach ($value as $keyValue => $subresources) {
            if (!is_array($subresources)) {
                throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
            }

            if ($this->possibleKey && !$isNumeric) {
                $subresources[$this->possibleKey] = $keyValue;
            }

            $this->writeResource->extract(
                $subresources,
                $this->referenceClass,
                $this->exceptionStack,
                $this->commandQueue,
                $this->writeContext,
                $this->fieldExtenderCollection,
                $this->path . '/' . $key . '/' . $keyValue
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
    }
}
