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

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\ORM\Write\FieldException\MalformatDataException;

class ReferenceField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $referenceField;

    /**
     * @var string
     */
    private $referenceClass;

    /**
     * @var string
     */
    private $storageName;

    public function __construct(string $storageName, string $propertyName, string $referenceField, string $referenceClass)
    {
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
        $this->referenceClass = $referenceClass;
        parent::__construct($propertyName);
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getExtractPriority(): int
    {
        return 80;
    }

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!is_array($value)) {
            throw new MalformatDataException($this->path, 'Expected array');
        }

        $this->writeResource->extract(
            $value,
            $this->referenceClass,
            $this->exceptionStack,
            $this->commandQueue,
            $this->writeContext,
            $this->fieldExtenderCollection,
            $this->path . '/' . $key
        );

        $id = $this->writeContext->get($this->referenceClass, $this->referenceField);

        $fkField = $this->definition::getFields()->getByStorageName($this->storageName);

        yield $fkField->getPropertyName() => $id;
    }
}
