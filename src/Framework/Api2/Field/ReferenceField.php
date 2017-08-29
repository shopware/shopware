<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Field;

use Shopware\Framework\Api2\FieldAware\ApiQueryQueueAware;
use Shopware\Framework\Api2\FieldAware\ExceptionStackAware;
use Shopware\Framework\Api2\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Api2\FieldAware\FieldExtenderCollectionAware;
use Shopware\Framework\Api2\FieldAware\PathAware;
use Shopware\Framework\Api2\FieldAware\ResourceRegistryAware;
use Shopware\Framework\Api2\FieldAware\SqlGatewayAware;
use Shopware\Framework\Api2\FieldAware\WriteContextAware;
use Shopware\Framework\Api2\FieldException\ApiFieldException;
use Shopware\Framework\Api2\FieldException\FieldExceptionStack;
use Shopware\Framework\Api2\FieldException\MalformatDataException;
use Shopware\Framework\Api2\Query\ApiQueryQueue;
use Shopware\Framework\Api2\Resource\ResourceRegistry;
use Shopware\Framework\Api2\SqlGateway;
use Shopware\Framework\Api2\WriteContext;

class ReferenceField extends Field implements PathAware, FieldExtenderCollectionAware, ResourceRegistryAware, ExceptionStackAware, ApiQueryQueueAware, WriteContextAware, SqlGatewayAware
{
    /**
     * @var string
     */
    private $foreignFieldName;
    /**
     * @var string
     */
    private $foreignClassName;

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
     * @var ApiQueryQueue
     */
    private $queryQueue;

    /**
     * @var string
     */
    private $localFieldName;

    /**
     * @var SqlGateway
     */
    private $sqlGateway;

    /**
     * @var FieldExtenderCollection
     */
    private $fieldExtenderCollection;

    /**
     * @var string
     */
    private $path;

    /**
     * @param string $localFieldName
     * @param string $foreignFieldName
     * @param string $foreignClassName
     */
    public function __construct(string $localFieldName, string $foreignFieldName, string $foreignClassName)
    {
        $this->localFieldName = $localFieldName;
        $this->foreignFieldName = $foreignFieldName;
        $this->foreignClassName = $foreignClassName;
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
     * @param string $type
     * @param string $key
     * @param null $value
     * @return \Generator
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (!is_array($value)) {
            throw new MalformatDataException($this->path, 'Expected array');
        }

        $referencedResource = $this->resourceRegistry
            ->get($this->foreignClassName);

        $referencedResource->extract(
            $value,
            $this->exceptionStack,
            $this->queryQueue,
            $this->sqlGateway,
            $this->writeContext,
            $this->fieldExtenderCollection,
            $this->path . '/' . $key
        );

        yield $this->localFieldName => $this->writeContext->get($this->foreignClassName, $this->foreignFieldName);
    }
}
