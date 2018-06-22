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

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\RepositoryInterface;

class OrderPersister implements OrderPersisterInterface
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var OrderConverter
     */
    private $converter;

    public function __construct(RepositoryInterface $repository, OrderConverter $converter)
    {
        $this->repository = $repository;
        $this->converter = $converter;
    }

    public function persist(CalculatedCart $calculatedCart, CheckoutContext $context): EntityWrittenContainerEvent
    {
        $order = $this->converter->convert($calculatedCart, $context);

        return $this->repository->create([$order], $context->getContext());
    }
}
