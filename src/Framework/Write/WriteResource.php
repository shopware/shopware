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

namespace Shopware\Framework\Write;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\DataStack\DataStack;
use Shopware\Framework\Write\DataStack\ExceptionNoStackItemFound;
use Shopware\Framework\Write\DataStack\KeyValuePair;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\Field;
use Shopware\Framework\Write\FieldAware\ExceptionStackAware;
use Shopware\Framework\Write\FieldAware\FieldExtender;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Write\FieldAware\PathAware;
use Shopware\Framework\Write\FieldAware\ResourceAware;
use Shopware\Framework\Write\FieldAware\WriteContextAware;
use Shopware\Framework\Write\FieldAware\WriteQueryQueueAware;
use Shopware\Framework\Write\FieldException\FieldExceptionStack;
use Shopware\Framework\Write\FieldException\WriteFieldException;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Query\InsertQuery;
use Shopware\Framework\Write\Query\UpdateQuery;
use Shopware\Framework\Write\Query\WriteQueryQueue;

abstract class WriteResource
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
        WriteQueryQueue $queryQueue,
        SqlGateway $sqlGateway,
        WriteContext $writeContext,
        FieldExtenderCollection $extenderCollection,
        string $path = ''
    ): array {
        $this->extendExtender($exceptionStack, $queryQueue, $writeContext, $extenderCollection, $path);

        $queryQueue->updateOrder(get_class($this), ...$this->getWriteOrder());

        $pkData = $this->map($this->primaryKeyFields, $rawData, self::FOR_INSERT, $exceptionStack, $extenderCollection);

        $type = $this->determineType($sqlGateway, $pkData);

        $rawData = $this->integrateDefaults($rawData, $type);

        $data = $this->map($this->fields, $rawData, $type, $exceptionStack, $extenderCollection);

        $this->updateQueryStack($queryQueue, $type, $pkData, $data);

        return $pkData;
    }

    public function getDefaults(string $type): array
    {
        return [];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context)
    {
    }

    /**
     * @param array               $fields
     * @param array               $rawData
     * @param string              $type
     * @param FieldExceptionStack $exceptionStack
     * @param FieldExtender       $fieldExtender
     *
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
                if (!$field->is(Required::class) || $type === self::FOR_UPDATE) {
                    continue;
                }

                $kvPair = new KeyValuePair($key, null, true);
            }

            $kvPair = $this->convertValue($field, $kvPair);

            $fieldExtender->extend($field);

            try {
                foreach ($field($type, $kvPair->getKey(), $kvPair->getValue()) as $fieldKey => $fieldValue) {
                    $stack->update($fieldKey, $fieldValue);
                }
            } catch (WriteFieldException $e) {
                $exceptionStack->add($e);
            }
        }

        return $stack->getResultAsArray();
    }

    /**
     * @param FieldExceptionStack     $exceptionStack
     * @param WriteQueryQueue         $queryQueue
     * @param WriteContext            $writeContext
     * @param FieldExtenderCollection $extenderCollection
     * @param string                  $path
     */
    private function extendExtender(FieldExceptionStack $exceptionStack, WriteQueryQueue $queryQueue, WriteContext $writeContext, FieldExtenderCollection $extenderCollection, string $path): void
    {
        $extenderCollection->addExtender(new class($this, $writeContext, $queryQueue, $exceptionStack, $path) extends FieldExtender {
            /**
             * @var resource
             */
            private $resource;
            /**
             * @var WriteContext
             */
            private $writeContext;
            /**
             * @var WriteQueryQueue
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
                WriteResource $resource,
                WriteContext $writeContext,
                WriteQueryQueue $queryQueue,
                FieldExceptionStack $exceptionStack,
                string $path
            ) {
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

                if ($field instanceof WriteQueryQueueAware) {
                    $field->setWriteQueryQueue($this->queryQueue);
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

    /**
     * @param array $rawData
     * @param $type
     *
     * @return array
     */
    private function integrateDefaults(array $rawData, $type): array
    {
        $defaults = $this->getDefaults($type);

        foreach ($defaults as $key => $value) {
            if (array_key_exists($key, $rawData)) {
                continue;
            }

            $rawData[$key] = $value;
        }

        return $rawData;
    }

    /**
     * @param SqlGateway $sqlGateway
     * @param array      $pkData
     *
     * @return string
     */
    private function determineType(SqlGateway $sqlGateway, array $pkData): string
    {
        $type = self::FOR_UPDATE;

        if (!$sqlGateway->exists($this->tableName, $pkData)) {
            $type = self::FOR_INSERT;
        }

        return $type;
    }

    /**
     * @param WriteQueryQueue $queryQueue
     * @param string          $type
     * @param array           $pkData
     * @param array           $data
     */
    private function updateQueryStack(WriteQueryQueue $queryQueue, string $type, array $pkData, array $data): void
    {
        if ($type === self::FOR_UPDATE) {
            $queryQueue->add(get_class($this), new UpdateQuery($this->tableName, $pkData, $data));
        } else {
            $queryQueue->add(get_class($this), new InsertQuery($this->tableName, array_merge($pkData, $data)));
        }
    }

    private function convertValue(Field $field, KeyValuePair $kvPair): KeyValuePair
    {
        if ($field instanceof DateField && is_string($kvPair->getValue())) {
            $kvPair = new KeyValuePair($kvPair->getKey(), new \DateTime($kvPair->getValue()), $kvPair->isRaw());
        }

        return $kvPair;
    }
}
