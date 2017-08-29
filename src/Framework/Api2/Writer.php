<?php declare(strict_types=1);

namespace Shopware\Framework\Api2;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Api2\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Api2\FieldException\FieldExceptionStack;
use Shopware\Framework\Api2\Query\ApiQueryQueue;
use Shopware\Framework\Api2\Resource\ApiResourceProduct;
use Shopware\Framework\Api2\Resource\ApiResourceProductDetail;
use Shopware\Framework\Api2\Resource\ApiResourceProductManufacturer;
use Shopware\Framework\Api2\Resource\ApiResourceProductTranslation;
use Shopware\Framework\Api2\Resource\ResourceRegistry;

class Writer
{
    /**
     * @var ResourceRegistry
     */
    private $resourceRegistry;

    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var SqlGateway
     */
    private $sqlGateway;

    public function __construct(
        SqlGateway $sqlGateway,
        ResourceRegistry $resourceRegistry,
        Connection $connection
    ) {
        $this->resourceRegistry = $resourceRegistry;
        $this->connection = $connection;
        $this->sqlGateway = $sqlGateway;
    }

    public function upsert(string $resourceClass, array $rawData, WriteContext $writeContext, FieldExtenderCollection $extender)
    {
        $resource = $this->resourceRegistry->get($resourceClass);

        $exceptionStack = new FieldExceptionStack();
        $queryQueue = new ApiQueryQueue();

        $queryQueue->setOrder(
            ApiResourceProductManufacturer::class,
            ApiResourceProduct::class,
            ApiResourceProductDetail::class,
            ApiResourceProductTranslation::class
        );

        $resource->extract($rawData, $exceptionStack, $queryQueue, $this->sqlGateway, $writeContext, $extender);

        $exceptionStack->tryToThrow();
        $queryQueue->execute($this->connection);
    }

    public function insert(string $resourceClass, array $rawData, WriteContext $writeContext, FieldExtenderCollection $extender)
    {
        $resource = $this->resourceRegistry->get($resourceClass);

        $exceptionStack = new FieldExceptionStack();
        $queryQueue = new ApiQueryQueue();

        $queryQueue->setOrder(
            ... $resource->getWriteOrder()
        );

        $resource->extract($rawData, $exceptionStack, $queryQueue, $this->sqlGateway, $writeContext, $extender);

        $exceptionStack->tryToThrow();

        $queryQueue->execute($this->connection);
    }

    public function update(string $resourceClass, array $rawData, WriteContext $writeContext, FieldExtenderCollection $extender)
    {
        $resource = $this->resourceRegistry->get($resourceClass);

        $exceptionStack = new FieldExceptionStack();
        $queryQueue = new ApiQueryQueue();

        $queryQueue->setOrder(
            ... $resource->getWriteOrder()
        );

        $resource->extract($rawData, $exceptionStack, $queryQueue, $this->sqlGateway, $writeContext, $extender);

        $exceptionStack->tryToThrow();

        $queryQueue->execute($this->connection);
    }
}