<?php

declare(strict_types=1);
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

namespace Shopware\Framework\Filesystem\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Azure\AzureAdapter;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AzureFactory implements AdapterFactoryInterface
{
    public function create(array $config): AdapterInterface
    {
        $options = $this->resolveAzureOptions($config);

        $endpoint = sprintf(
            'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s',
            $options['accountName'],
            $options['apiKey']
        );

        $blobRestProxy = ServicesBuilder::getInstance()->createBlobService($endpoint);

        return new AzureAdapter($blobRestProxy, $options['container'], $options['root']);
    }

    public function getType(): string
    {
        return 'microsoft-azure';
    }

    private function resolveAzureOptions(array $definition): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['accountName', 'apiKey', 'container']);
        $options->setDefined(['root']);

        $options->setAllowedTypes('accountName', 'string');
        $options->setAllowedTypes('apiKey', 'string');
        $options->setAllowedTypes('container', 'string');
        $options->setAllowedTypes('root', 'string');

        $options->setDefault('root', '');

        return $options->resolve($definition);
    }
}
