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
use Shopware\Api\Write\FieldAware\WriteContextAware;
use Shopware\Api\Write\FieldAware\WriteQueryQueueAware;
use Shopware\Api\Write\FieldException\FieldExceptionStack;
use Shopware\Api\Write\FieldException\MalformatDataException;
use Shopware\Api\Write\Query\WriteQueryQueue;
use Shopware\Api\Write\WriteContext;
use Shopware\Api\Write\WriteResource;

class SubresourceField extends Field implements PathAware, ExceptionStackAware, WriteQueryQueueAware, WriteContextAware, FieldExtenderCollectionAware
{
    /**
     * @var string
     */
    protected $referenceClass;

    /**
     * @var WriteQueryQueue
     */
    private $queryQueue;

    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var FieldExceptionStack
     */
    private $exceptionStack;

    /**
     * @var
     */
    private $possibleKey;

    /**
     * @var string
     */
    private $path;

    /**
     * @var FieldExtenderCollection
     */
    private $fieldExtender;

    public function __construct(string $propertyName, string $referenceClass, $possibleKey = null)
    {
        $this->referenceClass = $referenceClass;
        $this->possibleKey = $possibleKey;
        parent::__construct($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
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

            WriteResource::extract(
                $subresources,
                $this->referenceClass,
                $this->exceptionStack,
                $this->queryQueue,
                $this->writeContext,
                $this->fieldExtender,
                $this->path . '/' . $key . '/' . $keyValue
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
    }

    public function collectPrimaryKeys(string $type, string $key, $value = null): \Generator
    {
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

            WriteResource::collectPrimaryKeys(
                $subresources,
                $this->referenceClass,
                $this->exceptionStack,
                $this->queryQueue,
                $this->writeContext,
                $this->fieldExtender,
                $this->path . '/' . $key . '/' . $keyValue
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
    }

    /**
     * @param WriteContext $writeContext
     */
    public function setWriteContext(WriteContext $writeContext): void
    {
        $this->writeContext = $writeContext;
    }

    /**
     * @param FieldExceptionStack $exceptionStack
     */
    public function setExceptionStack(FieldExceptionStack $exceptionStack): void
    {
        $this->exceptionStack = $exceptionStack;
    }

    /**
     * @param WriteQueryQueue $queryQueue
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

    public function setFieldExtenderCollection(FieldExtenderCollection $fieldExtenderCollection): void
    {
        $this->fieldExtender = $fieldExtenderCollection;
    }
}
