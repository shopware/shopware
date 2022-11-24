<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter;
use League\Flysystem\FilesystemAdapter;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @package core
 */
class AwsS3v3Factory implements AdapterFactoryInterface
{
    public function create(array $config): FilesystemAdapter
    {
        $options = $this->resolveS3Options($config);

        $s3Opts = [];

        if (\array_key_exists('region', $options)) {
            $s3Opts['region'] = $options['region'];
        }

        if (\array_key_exists('endpoint', $options)) {
            $s3Opts['endpoint'] = $options['endpoint'];
        }

        if (\array_key_exists('use_path_style_endpoint', $options)) {
            $s3Opts['pathStyleEndpoint'] = $options['use_path_style_endpoint'];
        }

        if (isset($options['credentials'])) {
            $s3Opts['accessKeyId'] = $options['credentials']['key'];
            $s3Opts['accessKeySecret'] = $options['credentials']['secret'];
        }

        $client = new S3Client($s3Opts);

        return new AsyncAwsS3Adapter($client, $options['bucket'], $options['root'], new PortableVisibilityConverter());
    }

    public function getType(): string
    {
        return 'amazon-s3';
    }

    private function resolveS3Options(array $definition): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['bucket', 'region']);
        $options->setDefined(['credentials', 'root', 'options', 'endpoint', 'use_path_style_endpoint', 'url', 'visibility']);

        $options->setAllowedTypes('credentials', 'array');
        $options->setAllowedTypes('region', 'string');
        $options->setAllowedTypes('root', 'string');
        $options->setAllowedTypes('options', 'array');
        $options->setAllowedTypes('endpoint', 'string');
        $options->setAllowedTypes('use_path_style_endpoint', 'bool');

        $options->setDefault('root', '');
        $options->setDefault('options', []);

        $config = $options->resolve($definition);

        if (\array_key_exists('credentials', $config)) {
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
