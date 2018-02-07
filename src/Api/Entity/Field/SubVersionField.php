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

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Version\Definition\VersionDefinition;
use Shopware\Defaults;

class SubVersionField extends FkField
{
    public function __construct(string $referenceClass, string $storageName = 'version_id', string $propertyName = 'versionId')
    {
        parent::__construct($storageName, $propertyName, $referenceClass);

        $this->setFlags(new Required(), new PrimaryKey());
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(EntityExistence $existence, KeyValuePair $kvPair): \Generator
    {
        $value = $this->writeContext->get($this->referenceClass, 'versionId');

        yield $this->storageName => Uuid::fromString($value)->getBytes();
    }

    public function getExtractPriority(): int
    {
        return -100;
    }

}
