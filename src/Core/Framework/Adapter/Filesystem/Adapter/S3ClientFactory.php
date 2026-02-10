<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\S3\S3Client;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @phpstan-type S3Config array{bucket: string, region: string, root: string, credentials?: array{key: string, secret: string}, endpoint?: string, options?: array<mixed>, use_path_style_endpoint?: bool, visibility?: string, url?: string}
 *
 * @internal
 */
#[Package('framework')]
class S3ClientFactory
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array{client: S3Client, bucket: string, root: string}
     */
    public static function create(array $config): array
    {
        $options = self::resolveS3Options($config);

        $clientConfig = [
            'region' => $options['region'],
        ];

        if (\array_key_exists('endpoint', $options) && $options['endpoint']) {
            $clientConfig['endpoint'] = $options['endpoint'];
        }

        if (\array_key_exists('use_path_style_endpoint', $options)) {
            $clientConfig['pathStyleEndpoint'] = (string) $options['use_path_style_endpoint'];
        }

        if (isset($options['credentials'])) {
            $clientConfig['accessKeyId'] = $options['credentials']['key'];
            $clientConfig['accessKeySecret'] = $options['credentials']['secret'];
        }

        return [
            'client' => new S3Client($clientConfig),
            'bucket' => $options['bucket'],
            'root' => $options['root'],
        ];
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return S3Config
     */
    private static function resolveS3Options(array $definition): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['bucket', 'region']);
        $options->setDefined(['credentials', 'root', 'options', 'endpoint', 'use_path_style_endpoint', 'url', 'visibility']);

        $options->setAllowedTypes('credentials', ['array', 'null']);
        $options->setAllowedTypes('region', 'string');
        $options->setAllowedTypes('root', 'string');
        $options->setAllowedTypes('options', 'array');
        $options->setAllowedTypes('endpoint', 'string');
        $options->setAllowedTypes('use_path_style_endpoint', 'bool');

        $options->setDefault('root', '');
        $options->setDefault('options', []);

        /** @var S3Config $config */
        $config = $options->resolve($definition);

        if (\array_key_exists('credentials', $config) && $config['credentials'] !== null && $config['credentials'] !== []) {
            $config['credentials'] = self::resolveCredentialsOptions($config['credentials']);
        } else {
            unset($config['credentials']);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $credentials
     *
     * @return array{key: string, secret: string}
     */
    private static function resolveCredentialsOptions(array $credentials): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['key', 'secret']);

        $options->setAllowedTypes('key', 'string');
        $options->setAllowedTypes('secret', 'string');

        /** @var array{key: string, secret: string} $resolved */
        $resolved = $options->resolve($credentials);

        return $resolved;
    }
}
