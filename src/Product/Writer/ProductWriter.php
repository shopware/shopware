<?php
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

namespace Shopware\Product\Writer;

use Shopware\Framework\Write\FieldAware\DefaultExtender;
use Shopware\Framework\Write\FieldAware\FieldExtender;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Write\WriteContext;
use Shopware\Framework\Write\Writer;
use Shopware\Product\Writer\Resource\ProductResource;

class ProductWriter
{
    /**
     * @var Writer
     */
    private $writer;

    /**
     * @var DefaultExtender
     */
    private $extender;

    public function __construct(Writer $writer, DefaultExtender $extender)
    {
        $this->writer = $writer;
        $this->extender = $extender;
    }

    public function update(array $data, WriteContext $context, FieldExtender $extender = null): array
    {
        $extenderCollection = new FieldExtenderCollection();
        $extenderCollection->addExtender($this->extender);

        if ($extender) {
            $extenderCollection->addExtender($extender);
        }

        return $this->writer->update(ProductResource::class, $data, $context, $extenderCollection);
    }

    public function create(array $data, WriteContext $context, FieldExtender $extender = null): array
    {
        $extenderCollection = new FieldExtenderCollection();
        $extenderCollection->addExtender($this->extender);

        if ($extender) {
            $extenderCollection->addExtender($extender);
        }

        return $this->writer->insert(ProductResource::class, $data, $context, $extenderCollection);
    }
}
