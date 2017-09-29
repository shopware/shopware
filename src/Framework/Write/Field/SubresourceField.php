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

namespace Shopware\Framework\Write\Field;

use Shopware\Framework\Write\FieldAware\ExceptionStackAware;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollectionAware;
use Shopware\Framework\Write\FieldAware\PathAware;
use Shopware\Framework\Write\FieldAware\ResourceRegistryAware;
use Shopware\Framework\Write\FieldAware\SqlGatewayAware;
use Shopware\Framework\Write\FieldAware\WriteContextAware;
use Shopware\Framework\Write\FieldAware\WriteQueryQueueAware;
use Shopware\Framework\Write\FieldException\FieldExceptionStack;
use Shopware\Framework\Write\FieldException\MalformatDataException;
use Shopware\Framework\Write\Query\WriteQueryQueue;
use Shopware\Framework\Write\ResourceRegistry;
use Shopware\Framework\Write\SqlGateway;
use Shopware\Framework\Write\WriteContext;

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

    /**
     * {@inheritdoc}
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (!is_array($value)) {
            throw new MalformatDataException($this->path, 'WriteResource Must be an array.');
        }

        $isNumeric = array_keys($value) === range(0, count($value) - 1);

        $resource = $this->resourceRegistry
            ->get($this->resourceClass);

        foreach ($value as $keyValue => $subresources) {
            if (!is_array($subresources)) {
                throw new MalformatDataException($this->path, 'WriteResource Must be an array.');
            }

            if ($this->possibleKey && !$isNumeric) {
                $subresources[$this->possibleKey] = $keyValue;
            }

            $resource->extract(
                $subresources,
                $this->exceptionStack,
                $this->queryQueue,
                $this->sqlGateway,
                $this->writeContext,
                $this->fieldExtenderCollection,
                $this->path . '/' . $key . '/' . $keyValue
            );
        }
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
