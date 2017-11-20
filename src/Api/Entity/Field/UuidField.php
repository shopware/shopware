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

use Shopware\Api\Write\FieldAware\DefinitionAware;
use Shopware\Api\Write\FieldAware\StorageAware;
use Shopware\Api\Write\FieldAware\UuidGeneratorRegistryAware;
use Shopware\Api\Write\FieldAware\WriteContextAware;
use Shopware\Api\Write\UuidGenerator\GeneratorRegistry;
use Shopware\Api\Write\UuidGenerator\RamseyGenerator;
use Shopware\Api\Write\WriteContext;

class UuidField extends Field implements WriteContextAware, DefinitionAware, UuidGeneratorRegistryAware, StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var string
     */
    private $definition;

    /**
     * @var GeneratorRegistry
     */
    private $generatorRegistry;

    /**
     * @var string
     */
    private $generatorClass;

    public function __construct(string $storageName, string $propertyName, string $generatorClass = RamseyGenerator::class)
    {
        $this->storageName = $storageName;
        $this->generatorClass = $generatorClass;
        parent::__construct($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (!$value) {
            $value = $this->generatorRegistry
                ->get($this->generatorClass)
                ->create();
        }

        $this->writeContext->set($this->definition, $key, $value);

        yield $this->storageName => $value;
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
    public function setDefinition(string $definition): void
    {
        $this->definition = $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function setUuidGeneratorRegistry(GeneratorRegistry $generatorRegistry): void
    {
        $this->generatorRegistry = $generatorRegistry;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }
}
