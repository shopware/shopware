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

namespace Shopware\Storefront\Controller;

use Shopware\Serializer\SerializerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\Controller as SymfonyController;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller extends SymfonyController
{
    protected function render($view, array $parameters = [], Response $response = null): Response
    {
        //remove static template inheritance prefix
        if (strpos($view, '@') === 0) {
            $view = explode('/', $view);
            array_shift($view);
            $view = implode('/', $view);
        }

        $template = $this->get('shopware.storefront.twig.template_finder')->find($view, true);

        return parent::render($template, $parameters, $response);
    }

    protected function serialize($data): array
    {
        return $this->container->get('shopware.serializer.serializer_registry')
            ->serialize($data, SerializerRegistry::FORMAT_ARRAY);
    }
}
