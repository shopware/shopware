<?php
declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\Cart\CartCollectorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Content\Product\Aggregate\ProductService\Collection\ProductServiceBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceRepository;
use Shopware\Core\Content\Product\Cart\Struct\ProductFetchDefinition;
use Shopware\Core\Content\Product\Cart\Struct\ProductServiceFetchDefinition;
use Shopware\Core\Content\Product\Collection\ProductBasicCollection;
use Shopware\Core\Framework\Struct\StructCollection;

class ProductCartCollector implements CartCollectorInterface
{
    /**
     * @var ProductGatewayInterface
     */
    private $productGateway;

    /**
     * @var ProductServiceRepository
     */
    private $serviceGateway;

    public function __construct(ProductGatewayInterface $productGateway, ProductServiceRepository $serviceGateway)
    {
        $this->productGateway = $productGateway;
        $this->serviceGateway = $serviceGateway;
    }

    public function prepare(StructCollection $fetchDefinition, Cart $cart, CustomerContext $context): void
    {
        $lineItems = $cart->getLineItems()->filterType(ProductProcessor::TYPE_PRODUCT);
        if ($lineItems->count() === 0) {
            return;
        }

        $payloads = $lineItems->getPayload();
        $identifiers = array_column($payloads, 'id');

        $fetchDefinition->add(new ProductFetchDefinition($identifiers));

        $serviceIds = $this->getServiceIds($payloads);

        if (!empty($serviceIds)) {
            $fetchDefinition->add(new ProductServiceFetchDefinition($serviceIds));
        }
    }

    public function fetch(StructCollection $dataCollection, StructCollection $fetchCollection, CustomerContext $context): void
    {
        $definitions = $fetchCollection->filterInstance(ProductFetchDefinition::class);
        if ($definitions->count() > 0) {
            $products = $this->fetchProducts($context, $definitions);
            $dataCollection->fill($products->getElements());
        }

        $definitions = $fetchCollection->filterInstance(ProductServiceFetchDefinition::class);
        if ($definitions->count() > 0) {
            $services = $this->fetchServices($context, $definitions);
            $dataCollection->fill($services->getElements());
        }
    }

    private function getServiceIds(array $payloads): array
    {
        $serviceIds = array_filter(array_column($payloads, 'services'));
        $flat = [];
        foreach ($serviceIds as $ids) {
            $flat = array_merge($flat, $ids);
        }

        return array_filter(array_keys(array_flip($flat)));
    }

    private function fetchProducts(CustomerContext $context, StructCollection $definitions): ProductBasicCollection
    {
        $ids = [];
        /** @var ProductFetchDefinition[] $definitions */
        foreach ($definitions as $definition) {
            $ids = array_merge($ids, $definition->getIds());
        }

        $ids = array_keys(array_flip($ids));

        return $this->productGateway->get($ids, $context);
    }

    private function fetchServices(CustomerContext $context, StructCollection $definitions): ProductServiceBasicCollection
    {
        $ids = [];
        /** @var ProductServiceFetchDefinition[] $definitions */
        foreach ($definitions as $definition) {
            $ids = array_merge($ids, $definition->getServiceIds());
        }

        $ids = array_keys(array_flip($ids));

        return $this->serviceGateway->readBasic($ids, $context->getContext());
    }
}
