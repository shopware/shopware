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

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Version\VersionDefinition;

class ReferenceVersionField extends FkField
{
    /**
     * @var EntityDefinition|string
     */
    protected $versionReference;

    public function __construct(string $definition, ?string $storageName = null)
    {
        /** @var string|EntityDefinition $definition */
        $entity = $definition::getEntityName();
        $storageName = $storageName ?? $entity . '_version_id';

        $propertyName = explode('_', $storageName);
        $propertyName = array_map('ucfirst', $propertyName);
        $propertyName = lcfirst(implode($propertyName));

        parent::__construct($storageName, $propertyName, VersionDefinition::class);

        $this->setFlags(new Required());
        $this->versionReference = $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(EntityExistence $existence, KeyValuePair $kvPair): \Generator
    {
        if ($this->definition === $this->versionReference) {
            //parent inheritance with versioning
            $value = $kvPair->getValue() ?? Defaults::LIVE_VERSION;
        } elseif ($this->writeContext->has($this->versionReference, 'versionId')) {
            $value = $this->writeContext->get($this->versionReference, 'versionId');
        } else {
            $value = Defaults::LIVE_VERSION;
        }

        yield $this->storageName => Uuid::fromStringToBytes($value);
    }

    public function getVersionReference(): string
    {
        return $this->versionReference;
    }
}
