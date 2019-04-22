<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Filesystem;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\Filesystem\Adapter\AdapterFactoryInterface;
use Shopware\Core\Framework\Filesystem\Exception\AdapterFactoryNotFoundException;
use Shopware\Core\Framework\Filesystem\Exception\DuplicateFilesystemFactoryException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilesystemFactory
{
    /**
     * @var AdapterFactoryInterface[]
     */
    private $adapterFactories;

    /**
     * @param AdapterFactoryInterface[]|iterable $adapterFactories
     *
     * @throws DuplicateFilesystemFactoryException
     */
    public function __construct(iterable $adapterFactories)
    {
        $this->checkDuplicates($adapterFactories);
        $this->adapterFactories = $adapterFactories;
    }

    public function factory(array $config): FilesystemInterface
    {
        $config = $this->resolveFilesystemConfig($config);
        $factory = $this->findAdapterFactory($config['type']);

        return new LeagueFilesystem(
            $factory->create($config['config']),
            ['visibility' => $config['visibility']]
        );
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
            $type = strtolower($adapter->getType());
            if (array_key_exists($type, $dupes)) {
                throw new DuplicateFilesystemFactoryException($type);
            }

            $dupes[$type] = 1;
        }
    }

    private function resolveFilesystemConfig(array $config): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['type']);
        $options->setDefined(['config', 'visibility', 'disable_asserts']);

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
