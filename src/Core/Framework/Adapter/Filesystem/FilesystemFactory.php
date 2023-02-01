<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Visibility;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopware\Core\Framework\Adapter\Filesystem\Exception\AdapterFactoryNotFoundException;
use Shopware\Core\Framework\Adapter\Filesystem\Exception\DuplicateFilesystemFactoryException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[Package('core')]
class FilesystemFactory
{
    /**
     * @var AdapterFactoryInterface[]
     */
    private readonly iterable $adapterFactories;

    /**
     * @internal
     *
     * @param AdapterFactoryInterface[]|iterable $adapterFactories
     *
     * @throws DuplicateFilesystemFactoryException
     */
    public function __construct(iterable $adapterFactories)
    {
        $this->checkDuplicates($adapterFactories);
        $this->adapterFactories = $adapterFactories;
    }

    /**
     * @param array<mixed> $config
     */
    public function privateFactory(array $config): FilesystemOperator
    {
        $config['private'] = true;

        return $this->factory($config);
    }

    /**
     * @param array<mixed> $config
     */
    public function factory(array $config): FilesystemOperator
    {
        $config = $this->resolveFilesystemConfig($config);
        $factory = $this->findAdapterFactory($config['type']);

        if (isset($config['config']['options']['visibility'])) {
            $config['visibility'] = $config['config']['options']['visibility'];
            unset($config['config']['options']['visibility']);

            if ($config['config']['options'] === []) {
                unset($config['config']['options']);
            }
        }

        $fsOptions = [
            Config::OPTION_VISIBILITY => $config['visibility'],
            Config::OPTION_DIRECTORY_VISIBILITY => $config['visibility'],
        ];

        if (!$config['private']) {
            $fsOptions['public_url'] = $config['url'] ?? $this->getFallbackUrl();
        }

        return new LeagueFilesystem(
            $factory->create($config['config']),
            $fsOptions
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
            $type = mb_strtolower($adapter->getType());
            if (\array_key_exists($type, $dupes)) {
                throw new DuplicateFilesystemFactoryException($type);
            }

            $dupes[$type] = 1;
        }
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function resolveFilesystemConfig(array $config): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['type']);
        $options->setDefined(['config', 'visibility', 'disable_asserts', 'url', 'private']);

        $options->setDefault('config', []);
        $options->setDefault('visibility', Visibility::PUBLIC);
        $options->setDefault('disable_asserts', false);
        $options->setDefault('private', false);

        $options->setAllowedTypes('type', 'string');
        $options->setAllowedTypes('config', 'array');
        $options->setAllowedTypes('disable_asserts', 'bool');

        $options->setAllowedValues('visibility', [Visibility::PUBLIC, Visibility::PRIVATE]);

        return $options->resolve($config);
    }

    private function getFallbackUrl(): string
    {
        $request = Request::createFromGlobals();
        $basePath = $request->getSchemeAndHttpHost() . $request->getBasePath();
        $requestUrl = rtrim($basePath, '/') . '/';

        if ($request->getHost() === '' && EnvironmentHelper::getVariable('APP_URL')) {
            /** @var string $requestUrl */
            $requestUrl = EnvironmentHelper::getVariable('APP_URL');
        }

        return $requestUrl;
    }
}
