<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use Google\Cloud\Storage\StorageClient;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[Package('core')]
class GoogleStorageFactory implements AdapterFactoryInterface
{
    public function create(array $config): FilesystemAdapter
    {
        $options = $this->resolveStorageConfig($config);
        $storageConfig = ['projectId' => $options['projectId']];
        if (isset($config['keyFile'])) {
            $storageConfig['keyFile'] = $options['keyFile'];
        } else {
            $storageConfig['keyFilePath'] = $options['keyFilePath'];
        }

        $bucket = (new StorageClient($storageConfig))->bucket($options['bucket']);

        return new GoogleCloudStorageAdapter($bucket, $options['root']);
    }

    public function getType(): string
    {
        return 'google-storage';
    }

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
}
