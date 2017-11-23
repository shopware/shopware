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

namespace Shopware\Api\Write\Field;

use Shopware\Api\Write\FieldAware\ExceptionStackAware;
use Shopware\Api\Write\FieldAware\FieldExtenderCollection;
use Shopware\Api\Write\FieldAware\FieldExtenderCollectionAware;
use Shopware\Api\Write\FieldAware\PathAware;
use Shopware\Api\Write\FieldAware\ResourceRegistryAware;
use Shopware\Api\Write\FieldAware\SqlGatewayAware;
use Shopware\Api\Write\FieldAware\WriteContextAware;
use Shopware\Api\Write\FieldAware\WriteQueryQueueAware;
use Shopware\Api\Write\FieldException\FieldExceptionStack;
use Shopware\Api\Write\FieldException\MalformatDataException;
use Shopware\Api\Write\Query\WriteQueryQueue;
use Shopware\Api\Write\ResourceRegistry;
use Shopware\Api\Write\SqlGateway;
use Shopware\Api\Write\WriteContext;

class SubresourceField extends Field implements PathAware, FieldExtenderCollectionAware, ResourceRegistryAware, ExceptionStackAware, WriteQueryQueueAware, WriteContextAware, SqlGatewayAware
{
    /**
     * @var string
     */
    private $resourceClass;

    /**
     * @var WriteQueryQueue
     */
    private $queryQueue;

    /**
     * @var ResourceRegistry
     */
    private $resourceRegistry;

    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var FieldExceptionStack
     */
    private $exceptionStack;

    /**
     * @var SqlGateway
     */
    private $sqlGateway;

    /**
     * @var
     */
    private $possibleKey;

    /**
     * @var
     */
    private $fieldExtenderCollection;

    /**
     * @var string
     */
    private $path;

    /**
     * @param string $resourceClass
     */
    public function __construct(string $resourceClass, $possibleKey = null)
    {
        $this->resourceClass = $resourceClass;
        $this->possibleKey = $possibleKey;
    }

    public function collectPrimaryKeys(string $type, string $key, $value = null): \Generator
    {
        if (!is_array($value)) {
            throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
        }

        $isNumeric = array_keys($value) === range(0, count($value) - 1);

        $resource = $this->resourceRegistry
            ->get($this->resourceClass);

        foreach ($value as $keyValue => $subresources) {
            if (!is_array($subresources)) {
                throw new MalformatDataException($this->path.'/'.$key, 'Value must be an array.');
            }

            if ($this->possibleKey && !$isNumeric) {
                $subresources[$this->possibleKey] = $keyValue;
            }

            $resource->collectPrimaryKeys(
                $subresources,
                $this->exceptionStack,
                $this->queryQueue,
                $this->writeContext,
                $this->fieldExtenderCollection,
                $this->path.'/'.$key.'/'.$keyValue
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
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

        $resource = $this->resourceRegistry
            ->get($this->resourceClass);

        foreach ($value as $keyValue => $subresources) {
            if (!is_array($subresources)) {
                throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
            }

            if ($this->possibleKey && !$isNumeric) {
                $subresources[$this->possibleKey] = $keyValue;
            }

            $resource->extract(
                $subresources,
                $this->exceptionStack,
                $this->queryQueue,
                $this->writeContext,
                $this->fieldExtenderCollection,
                $this->path . '/' . $key . '/' . $keyValue
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
    }

    /**
     * @param ResourceRegistry $resourceRegistry
     */
    public function setResourceRegistry(ResourceRegistry $resourceRegistry): void
    {
        $this->resourceRegistry = $resourceRegistry;
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
     * @param SqlGateway $sqlGateway
     */
    public function setSqlGateway(SqlGateway $sqlGateway): void
    {
        $this->sqlGateway = $sqlGateway;
    }

    /**
     * {@inheritdoc}
     */
    public function setFieldExtenderCollection(FieldExtenderCollection $fieldExtenderCollection): void
    {
        $this->fieldExtenderCollection = $fieldExtenderCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function setPath(string $path = ''): void
    {
        $this->path = $path;
    }
}
