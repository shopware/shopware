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

namespace Shopware\Product\Gateway;

use Shopware\Context\TranslationContext;
use Shopware\Framework\Api2\Resource\ResourceRegistry;
use Shopware\Framework\Api2\FieldAware\DefaultExtender;
use Shopware\Framework\Api2\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Api2\Resource\CoreShopsResource;
use Shopware\Framework\Api2\WriteContext;
use Shopware\Framework\Api2\Writer;
use Shopware\Product\Exception\NotSupportedFetchMode;
use Shopware\Product\Gateway\Resource\ProductResource;
use Shopware\Product\Struct\ProductCollection;
use Shopware\Search\Criteria;
use Shopware\Search\SearchResultInterface;

class ProductRepository
{
    const RESOURCE = 'Product';

    const FETCH_MINIMAL = 'minimal';

    /**
     * @var ProductReader
     */
    private $reader;

    /**
     * @var ProductSearcher
     */
    private $searcher;

    /**
     * @var Writer
     */
    private $writer;

    /**
     * @var ResourceRegistry
     */
    private $resourceRegistry;

    /**
     * @var DefaultExtender
     */
    private $defaultExtender;

    /**
     * @param ProductReader $reader
     * @param ProductSearcher $searcher
     * @param Writer $writer
     * @param ResourceRegistry $resourceRegistry
     * @param DefaultExtender $defaultExtender
     */
    public function __construct(
        ProductReader $reader,
        ProductSearcher $searcher,
        Writer $writer,
        ResourceRegistry $resourceRegistry,
        DefaultExtender $defaultExtender
    ) {
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->writer = $writer;
        $this->resourceRegistry = $resourceRegistry;
        $this->defaultExtender = $defaultExtender;
    }

    public function search(Criteria $criteria, TranslationContext $context): SearchResultInterface
    {
        return $this->searcher->search($criteria, $context);
    }

    public function read(array $numbers, TranslationContext $context, string $fetchMode): ProductCollection
    {
        switch ($fetchMode) {
            case self::FETCH_MINIMAL:
                return $this->reader->read($numbers, $context);

            default:
                throw new NotSupportedFetchMode($fetchMode);
        }
    }

    public function create(array $data): array
    {
        $extender = new FieldExtenderCollection();
        $extender->addExtender($this->defaultExtender);

        $writeContext = new WriteContext();
        $writeContext->set(CoreShopsResource::class, 'uuid', 'SWAG-CONFIG-SHOP-UUID-1');

        return $this->writer
            ->insert(ProductResource::class, $data, $writeContext, $extender);
    }

    public function update(array $data): array
    {
        $extender = new FieldExtenderCollection();
        $extender->addExtender($this->defaultExtender);

        $writeContext = new WriteContext();
        $writeContext->set(CoreShopsResource::class, 'uuid', 'SWAG-CONFIG-SHOP-UUID-1');

        return $this->writer
            ->update(ProductResource::class, $data, $writeContext, $extender);
    }

    public function delete(array $data): array
    {
        throw new \Exception('method is not implemented yet');
    }
}
