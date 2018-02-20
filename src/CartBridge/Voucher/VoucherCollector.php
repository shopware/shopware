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

namespace Shopware\CartBridge\Voucher;

use Shopware\Cart\Cart\CollectorInterface;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\CartBridge\Voucher\Struct\VoucherData;
use Shopware\CartBridge\Voucher\Struct\VoucherFetchDefinition;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class VoucherCollector implements CollectorInterface
{
    /**
     * @var VoucherGatewayInterface
     */
    private $gateway;

    /**
     * @param VoucherGatewayInterface $gateway
     */
    public function __construct(VoucherGatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    public function prepare(
        StructCollection $fetchDefinition,
        Cart $cart,
        StorefrontContext $context
    ): void {
        $vouchers = $cart->getLineItems()->filterType(VoucherProcessor::TYPE_VOUCHER);

        if ($vouchers->count() === 0) {
            return;
        }

        $data = array_column($vouchers->getPayload(), 'code');
        $fetchDefinition->add(new VoucherFetchDefinition($data));
    }

    public function fetch(
        StructCollection $dataCollection,
        StructCollection $fetchCollection,
        StorefrontContext $context
    ): void {
        $definitions = $fetchCollection->filterInstance(VoucherFetchDefinition::class);

        if ($definitions->count() === 0) {
            return;
        }

        $codes = [];
        /** @var VoucherFetchDefinition[] $definitions */
        foreach ($definitions as $definition) {
            $codes = array_merge($codes, $definition->getCodes());
        }

        $vouchers = $this->gateway->get($codes, $context);

        /** @var VoucherData $voucher */
        foreach ($vouchers as $voucher) {
            $dataCollection->add($voucher, $voucher->getCode());
        }
    }
}
