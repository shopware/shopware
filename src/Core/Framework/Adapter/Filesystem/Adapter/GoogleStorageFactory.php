<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use Google\Cloud\Storage\StorageClient;
use League\Flysystem\AdapterInterface;
use Superbalist\Flysystem\GoogleStorage\GoogleStorageAdapter;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoogleStorageFactory implements AdapterFactoryInterface
{
    public function create(array $config): AdapterInterface
    {
        $options = $this->resolveStorageConfig($config);

        $storageClient = new StorageClient([
            'projectId' => $options['projectId'],
            'keyFilePath' => $options['keyFilePath'],
        ]);

        $bucket = $storageClient->bucket($options['bucket']);

        return new GoogleStorageAdapter($storageClient, $bucket, $options['root']);
    }

    public function getType(): string
    {
        return 'google-storage';
    }

    private function resolveStorageConfig(array $definition): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['projectId', 'keyFilePath', 'bucket']);
        $options->setDefined(['root']);

        $options->setAllowedTypes('projectId', 'string');
        $options->setAllowedTypes('keyFilePath', 'string');
        $options->setAllowedTypes('bucket', 'string');
        $options->setAllowedTypes('root', 'string');

        $options->setDefault('root', '');

        return $options->resolve($definition);
    }
}
