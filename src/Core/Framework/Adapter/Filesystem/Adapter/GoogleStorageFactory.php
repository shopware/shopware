<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\Storage\StorageClient;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[Package('framework')]
class GoogleStorageFactory implements AdapterFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): FilesystemAdapter
    {
        $this->validateDependencies();

        $options = $this->resolveStorageConfig($config);
        $storageConfig = ['projectId' => $options['projectId']];

        if (isset($options['keyFile']) && \is_array($options['keyFile'])) {
            $storageConfig['credentialsFetcher'] = new ServiceAccountCredentials(
                scope: [StorageClient::FULL_CONTROL_SCOPE],
                jsonKey: $options['keyFile'],
            );
        } elseif (isset($options['keyFilePath']) && \is_string($options['keyFilePath']) && $options['keyFilePath'] !== '') {
            $json = @file_get_contents($options['keyFilePath']);
            if ($json !== false) {
                try {
                    $serviceAccount = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
                    if (\is_array($serviceAccount)) {
                        $storageConfig['credentialsFetcher'] = new ServiceAccountCredentials(
                            scope: [StorageClient::FULL_CONTROL_SCOPE],
                            jsonKey: $serviceAccount,
                        );
                    }
                } catch (\JsonException) {
                    // Ignore invalid JSON and fall back to ADC
                }
            }
        }

        // @phpstan-ignore method.deprecated
        $bucket = (new StorageClient($storageConfig))->bucket($options['bucket']);

        return new GoogleCloudStorageAdapter($bucket, $options['root']);
    }

    public function getType(): string
    {
        return 'google-storage';
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    private function resolveStorageConfig(array $definition): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['projectId', 'bucket']);
        $options->setDefined(['root', 'keyFilePath', 'keyFile', 'options', 'url']);

        $options->setAllowedTypes('projectId', 'string');
        $options->setAllowedTypes('keyFilePath', 'string');
        $options->setAllowedTypes('keyFile', 'array');
        $options->setAllowedTypes('bucket', 'string');
        $options->setAllowedTypes('root', 'string');
        $options->setAllowedTypes('options', 'array');

        $options->setDefault('root', '');
        $options->setDefault('options', []);

        return $options->resolve($definition);
    }

    private function validateDependencies(): void
    {
        if (!class_exists(GoogleCloudStorageAdapter::class)) {
            throw AdapterException::missingDependency('league/flysystem-google-cloud-storage');
        }
    }
}
