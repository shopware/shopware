<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use Aws\S3\S3Client;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Symfony\Component\OptionsResolver\OptionsResolver;

class S3v3Factory implements AdapterFactoryInterface
{
    public function create(array $config): AdapterInterface
    {
        $options = $this->resolveS3Options($config);

        $client = new S3Client($options);

        return new AwsS3Adapter($client, $options['bucket'], $options['root'], $options['options']);
    }

    public function getType(): string
    {
        return 's3';
    }

    private function resolveS3Options(array $definition): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['bucket', 'region', 'endpoint']);
        $options->setDefined(['credentials', 'use_path_style_endpoint', 'version', 'root', 'options']);

        $options->setAllowedTypes('credentials', 'array');
        $options->setAllowedTypes('endpoint', 'string');
        $options->setAllowedTypes('use_path_style_endpoint', 'bool');
        $options->setAllowedTypes('region', 'string');
        $options->setAllowedTypes('version', 'string');
        $options->setAllowedTypes('root', 'string');
        $options->setAllowedTypes('options', 'array');

        $options->setDefault('version', 'latest');
        $options->setDefault('use_path_style_endpoint', true);
        $options->setDefault('root', '');
        $options->setDefault('options', []);

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
