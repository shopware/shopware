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

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Write\FieldAware\ExceptionStackAware;
use Shopware\Api\Write\FieldAware\FieldExtenderCollection;
use Shopware\Api\Write\FieldAware\FieldExtenderCollectionAware;
use Shopware\Api\Write\FieldAware\PathAware;
use Shopware\Api\Write\FieldAware\StorageAware;
use Shopware\Api\Write\FieldAware\WriteContextAware;
use Shopware\Api\Write\FieldAware\WriteQueryQueueAware;
use Shopware\Api\Write\FieldException\FieldExceptionStack;
use Shopware\Api\Write\FieldException\MalformatDataException;
use Shopware\Api\Write\Query\WriteQueryQueue;
use Shopware\Api\Write\WriteContext;
use Shopware\Api\Write\WriteResource;

class ReferenceField extends Field implements PathAware, ExceptionStackAware, WriteQueryQueueAware, WriteContextAware, FieldExtenderCollectionAware, StorageAware
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
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var FieldExceptionStack
     */
    private $exceptionStack;

    /**
     * @var WriteQueryQueue
     */
    private $queryQueue;

    /**
     * @var string
     */
    private $storageName;

    /**
     * @var string
     */
    private $path;

    /**
     * @var FieldExtenderCollection
     */
    private $fieldExtender;

    public function __construct(string $storageName, string $propertyName, string $referenceField, string $referenceClass)
    {
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
        $this->referenceClass = $referenceClass;
        parent::__construct($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (!is_array($value)) {
            throw new MalformatDataException($this->path, 'Expected array');
        }

        WriteResource::extract(
            $value,
            $this->referenceClass,
            $this->exceptionStack,
            $this->queryQueue,
            $this->writeContext,
            $this->fieldExtender,
            $this->path . '/' . $key
        );

        yield $this->storageName => $this->writeContext->get($this->referenceClass, $this->referenceField);
    }

    public function collectPrimaryKeys(string $type, string $key, $value = null): \Generator
    {
        if (!is_array($value)) {
            throw new MalformatDataException($this->path, 'Expected array');
        }

        WriteResource::collectPrimaryKeys(
            $value,
            $this->referenceClass,
            $this->exceptionStack,
            $this->queryQueue,
            $this->writeContext,
            $this->fieldExtender,
            $this->path . '/' . $key
        );

        yield $this->storageName => $this->writeContext->get($this->referenceClass, $this->referenceField);
    }

    /**
     * {@inheritdoc}
     */
    public function setWriteContext(WriteContext $writeContext): void
    {
        $this->writeContext = $writeContext;
    }

    /**
     * {@inheritdoc}
     */
    public function setExceptionStack(FieldExceptionStack $exceptionStack): void
    {
        $this->exceptionStack = $exceptionStack;
    }

    /**
     * {@inheritdoc}
     */
    public function setWriteQueryQueue(WriteQueryQueue $queryQueue): void
    {
        $this->queryQueue = $queryQueue;
    }

    /**
     * {@inheritdoc}
     */
    public function setPath(string $path = ''): void
    {
        $this->path = $path;
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function setFieldExtenderCollection(FieldExtenderCollection $fieldExtenderCollection): void
    {
        $this->fieldExtender = $fieldExtenderCollection;
    }
}
