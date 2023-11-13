<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[Package('core')]
class LocalFactory implements AdapterFactoryInterface
{
    public function create(array $config): FilesystemAdapter
    {
        $options = $this->resolveOptions($config);

        return new LocalFilesystemAdapter(
            $options['root'],
            PortableVisibilityConverter::fromArray([
                'file' => $options['file'],
                'dir' => $options['dir'],
            ]),

            // Write flags
            \LOCK_EX,

            // How to deal with links, either DISALLOW_LINKS or SKIP_LINKS
            // Disallowing them causes exceptions when encountered
            LocalFilesystemAdapter::DISALLOW_LINKS
        );
    }

    public function getType(): string
    {
        return 'local';
    }

    private function resolveOptions(array $config): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['root']);
        $options->setDefined(['file', 'dir', 'url']);

        $options->setAllowedTypes('root', 'string');
        $options->setAllowedTypes('file', 'array');
        $options->setAllowedTypes('dir', 'array');

        $options->setDefault('file', []);
        $options->setDefault('dir', []);

        $config = $options->resolve($config);
        $config['file'] = $this->resolveFilePermissions($config['file']);
        $config['dir'] = $this->resolveDirectoryPermissions($config['dir']);

        return $config;
    }

    private function resolveFilePermissions(array $permissions): array
    {
        $options = new OptionsResolver();

        $options->setDefined(['public', 'private']);

        $options->setAllowedTypes('public', 'int');
        $options->setAllowedTypes('private', 'int');

        $options->setDefault('public', 0666 & ~umask());
        $options->setDefault('private', 0600 & ~umask());

        return $options->resolve($permissions);
    }

    private function resolveDirectoryPermissions(array $permissions): array
    {
        $options = new OptionsResolver();

        $options->setDefined(['public', 'private']);

        $options->setAllowedTypes('public', 'int');
        $options->setAllowedTypes('private', 'int');

        $options->setDefault('public', 0777 & ~umask());
        $options->setDefault('private', 0700 & ~umask());

        return $options->resolve($permissions);
    }
}
