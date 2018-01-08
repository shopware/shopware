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

namespace Shopware\Api\Entity\Write;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ReferenceField;
use Shopware\Api\Entity\Field\SubresourceField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\DataStack\DataStack;
use Shopware\Api\Entity\Write\DataStack\ExceptionNoStackItemFound;
use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\FieldAware\DefinitionAware;
use Shopware\Api\Entity\Write\FieldAware\ExceptionStackAware;
use Shopware\Api\Entity\Write\FieldAware\FieldExtender;
use Shopware\Api\Entity\Write\FieldAware\FieldExtenderCollection;
use Shopware\Api\Entity\Write\FieldAware\PathAware;
use Shopware\Api\Entity\Write\FieldAware\WriteContextAware;
use Shopware\Api\Entity\Write\FieldAware\WriteQueryQueueAware;
use Shopware\Api\Entity\Write\FieldException\FieldExceptionStack;
use Shopware\Api\Entity\Write\FieldException\WriteFieldException;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Query\InsertQuery;
use Shopware\Api\Entity\Write\Query\UpdateQuery;
use Shopware\Api\Entity\Write\Query\WriteQueryQueue;

class WriteResource
{
    public const FOR_INSERT = 'insert';

    public const FOR_UPDATE = 'update';

    public static function collectPrimaryKeys(
        array $rawData,
        string $definition,
        FieldExceptionStack $exceptionStack,
        WriteQueryQueue $queryQueue,
        WriteContext $writeContext,
        FieldExtenderCollection $extender,
        string $path = ''
    ): void {
        $extender = clone $extender;
        self::extendExtender($exceptionStack, $definition, $queryQueue, $writeContext, $extender, $path);

        /* @var EntityDefinition $definition */
        $queryQueue->updateOrder($definition, ...$definition::getWriteOrder());

        $pkData = self::mapPrimaryKeys($definition::getPrimaryKeys(), $rawData, self::FOR_INSERT, $exceptionStack, $extender);

        $writeContext->addPrimaryKeyMapping($definition::getEntityName(), $pkData);

        $fields = $definition::getFields()->filter(
            function (Field $field) {
                return $field instanceof SubresourceField
                    || $field instanceof FkField
                    || $field instanceof ReferenceField
                    || $field instanceof TranslatedField;
            }
        );

        self::mapPrimaryKeys($fields, $rawData, self::FOR_INSERT, $exceptionStack, $extender);
    }

    public static function extract(
        array $rawData,
        string $definition,
        FieldExceptionStack $exceptionStack,
        WriteQueryQueue $queryQueue,
        WriteContext $writeContext,
        FieldExtenderCollection $extender,
        string $path = ''
    ): array {
        $extender = clone $extender;
        self::extendExtender($exceptionStack, $definition, $queryQueue, $writeContext, $extender, $path);

        /* @var EntityDefinition $definition */
        $queryQueue->updateOrder($definition, ...$definition::getWriteOrder());

        $pkData = self::map($definition::getPrimaryKeys(), $rawData, self::FOR_INSERT, $exceptionStack, $extender);

        $type = self::determineQueryType($definition::getEntityName(), $writeContext, $pkData);

        $rawData = self::integrateDefaults($definition, $rawData, $type);

        $fields = $definition::getFields()->getWritableFields();
        $fields = $fields->filter(function (Field $field) {
            return !$field->is(PrimaryKey::class);
        });

        $data = self::map($fields, $rawData, $type, $exceptionStack, $extender);

        self::updateQueryStack($definition, $queryQueue, $type, $pkData, $data);

        return $pkData;
    }

    private static function map(FieldCollection $fields, array $rawData, string $type, FieldExceptionStack $exceptionStack, FieldExtenderCollection $extender): array
    {
        $stack = new DataStack($rawData);

        foreach ($fields as $field) {
            try {
                $kvPair = $stack->pop($field->getPropertyName());
            } catch (ExceptionNoStackItemFound $e) {
                if (!$field->is(Required::class) || $type === self::FOR_UPDATE) {
                    continue;
                }

                $kvPair = new KeyValuePair($field->getPropertyName(), null, true);
            }

            $kvPair = self::convertValue($field, $kvPair);

            $extender->extend($field);

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

    private static function mapPrimaryKeys(FieldCollection $fields, array $rawData, string $type, FieldExceptionStack $exceptionStack, FieldExtenderCollection $extender): array
    {
        $stack = new DataStack($rawData);

        /** @var IdField|SubresourceField|FkField|ReferenceField $field */
        foreach ($fields as $field) {
            $key = $field->getPropertyName();

            try {
                $kvPair = $stack->pop($key);
            } catch (ExceptionNoStackItemFound $e) {
                if (!$field->is(Required::class) || $type === self::FOR_UPDATE) {
                    continue;
                }

                $kvPair = new KeyValuePair($key, null, true);
            }

            $kvPair = self::convertValue($field, $kvPair);

            $extender->extend($field);

            try {
                if ($field instanceof SubresourceField || $field instanceof ReferenceField) {
                    $values = $field->collectPrimaryKeys($type, $kvPair->getKey(), $kvPair->getValue());
                } else {
                    $values = $field($type, $kvPair->getKey(), $kvPair->getValue());
                }

                foreach ($values as $fieldKey => $fieldValue) {
                    $stack->update($fieldKey, $fieldValue);
                }
            } catch (WriteFieldException $e) {
                $exceptionStack->add($e);
            }
        }

        return $stack->getResultAsArray();
    }

    private static function integrateDefaults(string $definition, array $rawData, $type): array
    {
        /** @var EntityDefinition $definition */
        $defaults = $definition::getDefaults($type);

        foreach ($defaults as $key => $value) {
            if (array_key_exists($key, $rawData)) {
                continue;
            }

            $rawData[$key] = $value;
        }

        return $rawData;
    }

    private static function determineQueryType(string $tableName, WriteContext $writeContext, array $pkData): string
    {
        $exists = $writeContext->primaryKeyExists($tableName, $pkData);

        return $exists ? self::FOR_UPDATE : self::FOR_INSERT;
    }

    private static function updateQueryStack(string $definition, WriteQueryQueue $queryQueue, string $type, array $pkData, array $data): void
    {
        /* @var EntityDefinition $definition */
        if ($type === self::FOR_UPDATE) {
            $queryQueue->add($definition, new UpdateQuery($definition, $pkData, $data));
        } else {
            $queryQueue->add($definition, new InsertQuery($definition, array_merge($pkData, $data)));
        }
    }

    private static function convertValue(Field $field, KeyValuePair $kvPair): KeyValuePair
    {
        if ($field instanceof DateField && is_string($kvPair->getValue())) {
            $kvPair = new KeyValuePair($kvPair->getKey(), new \DateTime($kvPair->getValue()), $kvPair->isRaw());
        }

        return $kvPair;
    }

    private static function extendExtender(
        FieldExceptionStack $exceptionStack,
        string $definition,
        WriteQueryQueue $queryQueue,
        WriteContext $writeContext,
        FieldExtenderCollection $extenderCollection,
        string $path
    ): void {
        $extenderCollection->addExtender(
            new class($definition, $writeContext, $queryQueue, $exceptionStack, $path) extends FieldExtender {
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

                /**
                 * @var string
                 */
                private $definition;

                public function __construct(
                    string $definition,
                    WriteContext $writeContext,
                    WriteQueryQueue $queryQueue,
                    FieldExceptionStack $exceptionStack,
                    string $path
                ) {
                    $this->writeContext = $writeContext;
                    $this->queryQueue = $queryQueue;
                    $this->exceptionStack = $exceptionStack;
                    $this->path = $path;
                    $this->definition = $definition;
                }

                public function extend(Field $field): void
                {
                    if ($field instanceof DefinitionAware) {
                        $field->setDefinition($this->definition);
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
            }
        );
    }
}
