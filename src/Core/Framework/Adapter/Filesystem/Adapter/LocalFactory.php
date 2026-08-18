<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\Config;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[Package('framework')]
class LocalFactory implements FilesystemOperatorFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): FilesystemAdapter
    {
        return $this->createAdapter($this->resolveOptions($config));
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $filesystemOptions
     */
    public function createFilesystem(array $config, array $filesystemOptions): FilesystemOperator
    {
        $options = $this->resolveOptions($config);

        if (!$options['enforce_file_permissions']) {
            // The local adapter maps visibility to chmod calls after writes, moves, and copies.
            unset($filesystemOptions[Config::OPTION_VISIBILITY]);
            $filesystemOptions[Config::OPTION_RETAIN_VISIBILITY] = false;
        }

        return new LeagueFilesystem($this->createAdapter($options), $filesystemOptions);
    }

    public function getType(): string
    {
        return 'local';
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createAdapter(array $options): FilesystemAdapter
    {
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

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(array $config): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['root']);
        $options->setDefined(['file', 'dir', 'url', 'enforce_file_permissions']);

        $options->setAllowedTypes('root', 'string');
        $options->setAllowedTypes('file', 'array');
        $options->setAllowedTypes('dir', 'array');
        $options->setAllowedTypes('enforce_file_permissions', 'bool');

        $options->setDefault('file', []);
        $options->setDefault('dir', []);
        $options->setDefault('enforce_file_permissions', true);

        $config = $options->resolve($config);
        $config['file'] = $this->resolveFilePermissions($config['file']);
        $config['dir'] = $this->resolveDirectoryPermissions($config['dir']);

        return $config;
    }

    /**
     * @param array<string, mixed> $permissions
     *
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $permissions
     *
     * @return array<string, mixed>
     */
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
