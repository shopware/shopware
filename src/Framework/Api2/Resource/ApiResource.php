<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\DataStack\DataStack;
use Shopware\Framework\Api2\DataStack\ExceptionNoStackItemFound;
use Shopware\Framework\Api2\DataStack\KeyValuePair;
use Shopware\Framework\Api2\Field\Field;
use Shopware\Framework\Api2\FieldAware\ApiQueryQueueAware;
use Shopware\Framework\Api2\FieldAware\ExceptionStackAware;
use Shopware\Framework\Api2\FieldAware\FieldExtender;
use Shopware\Framework\Api2\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Api2\FieldAware\PathAware;
use Shopware\Framework\Api2\FieldAware\ResourceAware;
use Shopware\Framework\Api2\FieldAware\WriteContextAware;
use Shopware\Framework\Api2\FieldException\ApiFieldException;
use Shopware\Framework\Api2\FieldException\FieldExceptionStack;
use Shopware\Framework\Api2\Query\ApiQueryQueue;
use Shopware\Framework\Api2\Query\InsertQuery;
use Shopware\Framework\Api2\Query\UpdateQuery;
use Shopware\Framework\Api2\SqlGateway;
use Shopware\Framework\Api2\WriteContext;

abstract class ApiResource
{
    const FOR_INSERT = 'insert';

    const FOR_UPDATE = 'update';

    /**
     * @var Field[]
     */
    protected $fields = [];

    /**
     * @var Field[]
     */
    protected $primaryKeyFields = [];

    /**
     * @var string
     */
    private $tableName;

    /**
     * @param string $tableName
     */
    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    abstract public function getWriteOrder(): array;

    public function extract(
        array $rawData,
        FieldExceptionStack $exceptionStack,
        ApiQueryQueue $queryQueue,
        SqlGateway $sqlGateway,
        WriteContext $writeContext,
        FieldExtenderCollection $extenderCollection,
        string $path = ''
    ) {
        $this->extendExtender($exceptionStack, $queryQueue, $writeContext, $extenderCollection, $path);

        $pkData = $this->map($this->primaryKeyFields, $rawData, self::FOR_INSERT, $exceptionStack, $extenderCollection);

        $type = self::FOR_UPDATE;

        if(!$sqlGateway->exists($this->tableName, $pkData)) {
            $type = self::FOR_INSERT;
        }

        $data = $this->map($this->fields, $rawData, $type, $exceptionStack, $extenderCollection);

        if ($type === self::FOR_UPDATE) {
            $queryQueue->add(get_class($this), new UpdateQuery($this->tableName, $pkData, $data));
        } else {
            $queryQueue->add(get_class($this), new InsertQuery($this->tableName, array_merge($pkData, $data)));
        }

//        print_r(['class' => get_class($this), 'pkData' => $pkData, 'data' => $data]);
    }

    /**
     * @param array $fields
     * @param array $rawData
     * @param string $type
     * @param FieldExceptionStack $exceptionStack
     * @param FieldExtender $fieldExtender
     * @return array
     */
    private function map(
        array $fields,
        array $rawData,
        string $type,
        FieldExceptionStack $exceptionStack,
        FieldExtender $fieldExtender
    ): array {
        $stack = new DataStack($rawData);

        foreach ($fields as $key => $field) {
            try {
                $kvPair = $stack->pop($key);

            } catch (ExceptionNoStackItemFound $e) {
                if(!$field->is(Required::class) || $type === self::FOR_UPDATE) {
                    continue;
                }

                // @todo required fields only here
                $kvPair = new KeyValuePair($key, null, true);
            }

            $fieldExtender->extend($field);

            try {
                foreach ($field($type, $kvPair->getKey(), $kvPair->getValue()) as $fieldKey => $fieldValue) {
                    $stack->update($fieldKey, $fieldValue);
                }

            } catch (ApiFieldException $e) {
                $exceptionStack->add($e);
            }
        }

        return $stack->getResultAsArray();
    }

    /**
     * @param FieldExceptionStack $exceptionStack
     * @param ApiQueryQueue $queryQueue
     * @param WriteContext $writeContext
     * @param FieldExtenderCollection $extenderCollection
     * @param string $path
     */
    private function extendExtender(FieldExceptionStack $exceptionStack, ApiQueryQueue $queryQueue, WriteContext $writeContext, FieldExtenderCollection $extenderCollection, string $path): void
    {
        $extenderCollection->addExtender(new class($this, $writeContext, $queryQueue, $exceptionStack, $path) extends FieldExtender
        {
            /**
             * @var ApiResource
             */
            private $resource;
            /**
             * @var WriteContext
             */
            private $writeContext;
            /**
             * @var ApiQueryQueue
             */
            private $queryQueue;
            /**
             * @var FieldExceptionStack
             */
            private $exceptionStack;
            /**
             * @var string
             */
            private $path;

            public function __construct(
                ApiResource $resource,
                WriteContext $writeContext,
                ApiQueryQueue $queryQueue,
                FieldExceptionStack $exceptionStack,
                string $path
            )
            {
                $this->resource = $resource;
                $this->writeContext = $writeContext;
                $this->queryQueue = $queryQueue;
                $this->exceptionStack = $exceptionStack;
                $this->path = $path;
            }

            public function extend(Field $field): void
            {
                if ($field instanceof ResourceAware) {
                    $field->setResource($this->resource);
                }

                if ($field instanceof WriteContextAware) {
                    $field->setWriteContext($this->writeContext);
                }

                if ($field instanceof ApiQueryQueueAware) {
                    $field->setApiQueryQueue($this->queryQueue);
                }

                if ($field instanceof ExceptionStackAware) {
                    $field->setExceptionStack($this->exceptionStack);
                }

                if ($field instanceof PathAware) {
                    $field->setPath($this->path);
                }
            }
        });
    }
}