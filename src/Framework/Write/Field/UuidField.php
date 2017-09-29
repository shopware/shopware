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

use Shopware\Framework\Write\FieldAware\ResourceAware;
use Shopware\Framework\Write\FieldAware\UuidGeneratorRegistryAware;
use Shopware\Framework\Write\FieldAware\WriteContextAware;
use Shopware\Framework\Write\UuidGenerator\GeneratorRegistry;
use Shopware\Framework\Write\UuidGenerator\RamseyGenerator;
use Shopware\Framework\Write\WriteContext;
use Shopware\Framework\Write\WriteResource;

class UuidField extends Field implements WriteContextAware, ResourceAware, UuidGeneratorRegistryAware
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
     * @var resource
     */
    private $resource;

    /**
     * @var GeneratorRegistry
     */
    private $generatorRegistry;

    /**
     * @var string
     */
    private $generatorClass;

    /**
     * @param string $storageName
     * @param string $generatorClass
     */
    public function __construct(string $storageName, string $generatorClass = RamseyGenerator::class)
    {
        $this->storageName = $storageName;
        $this->generatorClass = $generatorClass;
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

        $this->writeContext->set(get_class($this->resource), $key, $value);

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
    public function setResource(WriteResource $resource): void
    {
        $this->resource = $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function setUuidGeneratorRegistry(GeneratorRegistry $generatorRegistry): void
    {
        $this->generatorRegistry = $generatorRegistry;
    }
}
