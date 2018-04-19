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

require __DIR__ . '/../../autoload.php';

class TestKernel extends \Shopware\Kernel
{
    /**
     * Static method to start boot kernel without leaving local scope in test helper
     */
    public static function start()
    {
        $kernel = new self('testing', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $container->get('plugins')->Core()->ErrorHandler()->registerErrorHandler(E_ALL | E_STRICT);

        /** @var $repository \Shopware\Models\Shop\Repository */
        $repository = $container->get('models')->getRepository('Shopware\Models\Shop\Shop');

        $shop = $repository->getActiveDefault();
        $shop->registerResources();

        $_SERVER['HTTP_HOST'] = $shop->getHost();
    }

    protected function getConfigPath()
    {
        return __DIR__ . '/config.php';
    }
}

TestKernel::start();
