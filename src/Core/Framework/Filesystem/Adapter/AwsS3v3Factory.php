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

namespace Shopware\Core\Framework\Filesystem\Adapter;

use Aws\S3\S3Client;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AwsS3v3Factory implements AdapterFactoryInterface
{
    public function create(array $config): AdapterInterface
    {
        $options = $this->resolveS3Options($config);

        $client = new S3Client($options);

        return new AwsS3Adapter($client, $options['bucket'], $options['root']);
    }

    public function getType(): string
    {
        return 'amazon-s3';
    }

    private function resolveS3Options(array $definition): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['bucket', 'region']);
        $options->setDefined(['credentials', 'version', 'root']);

        $options->setAllowedTypes('credentials', 'array');
        $options->setAllowedTypes('region', 'string');
        $options->setAllowedTypes('version', 'string');
        $options->setAllowedTypes('root', 'string');

        $options->setDefault('version', 'latest');
        $options->setDefault('root', '');

        $config = $options->resolve($definition);

        if (array_key_exists('credentials', $config)) {
            $config['credentials'] = $this->resolveCredentialsOptions($config['credentials']);
        }

        return $config;
    }

    private function resolveCredentialsOptions(array $credentials): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['key', 'secret']);

        $options->setAllowedTypes('key', 'string');
        $options->setAllowedTypes('secret', 'string');

        return $options->resolve($credentials);
    }
}
