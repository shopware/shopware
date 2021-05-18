<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopware\Core\Framework\Adapter\Filesystem\Exception\AdapterFactoryNotFoundException;
use Shopware\Core\Framework\Adapter\Filesystem\Exception\DuplicateFilesystemFactoryException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilesystemFactory
{
    /**
     * @var AdapterFactoryInterface[]
     */
    private $adapterFactories;

    /**
     * @var PluginInterface[]
     */
    private $filesystemPlugins;

    /**
     * @param AdapterFactoryInterface[]|iterable $adapterFactories
     * @param PluginInterface[]|iterable         $filesystemPlugins
     *
     * @throws DuplicateFilesystemFactoryException
     */
    public function __construct(iterable $adapterFactories, iterable $filesystemPlugins)
    {
        $this->checkDuplicates($adapterFactories);
        $this->adapterFactories = $adapterFactories;
        $this->filesystemPlugins = $filesystemPlugins;
    }

    public function factory(array $config): FilesystemInterface
    {
        $config = $this->resolveFilesystemConfig($config);
        $factory = $this->findAdapterFactory($config['type']);

        if (isset($config['config']['options']['visibility'])) {
            $config['visibility'] = $config['config']['options']['visibility'];
        }

        $filesystem = new LeagueFilesystem(
            $factory->create($config['config']),
            ['visibility' => $config['visibility']]
        );

        foreach ($this->filesystemPlugins as $plugin) {
            $plugin = clone $plugin;
            $plugin->setFilesystem($filesystem);
            $filesystem->addPlugin($plugin);
        }

        return $filesystem;
    }

    /**
     * @throws AdapterFactoryNotFoundException
     */
    private function findAdapterFactory(string $type): AdapterFactoryInterface
    {
        foreach ($this->adapterFactories as $factory) {
            if ($factory->getType() === $type) {
                return $factory;
            }
        }

        throw new AdapterFactoryNotFoundException($type);
    }

    /**
     * @param AdapterFactoryInterface[]|iterable $adapterFactories
     *
     * @throws DuplicateFilesystemFactoryException
     */
    private function checkDuplicates(iterable $adapterFactories): void
    {
        $dupes = [];
        foreach ($adapterFactories as $adapter) {
            $type = mb_strtolower($adapter->getType());
            if (\array_key_exists($type, $dupes)) {
                throw new DuplicateFilesystemFactoryException($type);
            }

            $dupes[$type] = 1;
        }
    }

    private function resolveFilesystemConfig(array $config): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['type']);
        $options->setDefined(['config', 'visibility', 'disable_asserts', 'url']);

        $options->setDefault('config', []);
        $options->setDefault('visibility', AdapterInterface::VISIBILITY_PUBLIC);
        $options->setDefault('disable_asserts', false);

        $options->setAllowedTypes('type', 'string');
        $options->setAllowedTypes('config', 'array');
        $options->setAllowedTypes('disable_asserts', 'bool');

        $options->setAllowedValues('visibility', [AdapterInterface::VISIBILITY_PUBLIC, AdapterInterface::VISIBILITY_PRIVATE]);

        return $options->resolve($config);
    }
}
