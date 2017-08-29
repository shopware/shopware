<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Field;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\FieldAware\ApiQueryQueueAware;
use Shopware\Framework\Api2\FieldAware\ExceptionStackAware;
use Shopware\Framework\Api2\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Api2\FieldAware\FieldExtenderCollectionAware;
use Shopware\Framework\Api2\FieldAware\PathAware;
use Shopware\Framework\Api2\FieldAware\ResourceRegistryAware;
use Shopware\Framework\Api2\FieldAware\SqlGatewayAware;
use Shopware\Framework\Api2\FieldAware\WriteContextAware;
use Shopware\Framework\Api2\FieldException\FieldExceptionStack;
use Shopware\Framework\Api2\FieldException\MalformatDataException;
use Shopware\Framework\Api2\Query\ApiQueryQueue;
use Shopware\Framework\Api2\Resource\ResourceRegistry;
use Shopware\Framework\Api2\SqlGateway;
use Shopware\Framework\Api2\WriteContext;

class SubresourceField extends Field implements PathAware, FieldExtenderCollectionAware, ResourceRegistryAware, ExceptionStackAware, ApiQueryQueueAware, WriteContextAware, SqlGatewayAware
{
    /**
     * @var string
     */
    private $resourceClass;

    /**
     * @var ApiQueryQueue
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
     * @param ApiQueryQueue $queryQueue
     */
    public function setApiQueryQueue(ApiQueryQueue $queryQueue): void
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


    public function setFieldExtenderCollection(FieldExtenderCollection $fieldExtenderCollection): void
    {
        $this->fieldExtenderCollection = $fieldExtenderCollection;
    }

    public function setPath(string $path = ''): void
    {
        $this->path = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (!is_array($value)) {
            throw new MalformatDataException($this->path, 'Resource Must be an array.');
        }

        $isNumeric = count(array_diff($value, range(0, count($value)))) === 0;

        $resource = $this->resourceRegistry
            ->get($this->resourceClass);

        foreach ($value as $keyValue => $subresources) {
            if (!is_array($subresources)) {
                throw new MalformatDataException($this->path, 'Resource Must be an array.');
            }

            if($this->possibleKey && !$isNumeric) {
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

        return;
        yield __CLASS__ => __METHOD__;
    }
}