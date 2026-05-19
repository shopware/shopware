<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Visibility;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopware\Core\Framework\Adapter\Filesystem\Exception\AdapterFactoryNotFoundException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[Package('framework')]
class FilesystemFactory
{
    /**
     * @param iterable<AdapterFactoryInterface> $adapterFactories
     *
     * @internal
     */
    public function __construct(private readonly iterable $adapterFactories)
    {
        $this->checkDuplicates($adapterFactories);
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

        // @deprecated tag:v6.8.0 - the visibility option will be removed from the filesystem config, it should be set next to the type
        if (isset($config['config']['options']['visibility'])) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Setting visibility in the filesystem config level is deprecated. Set visibility next to type: amazon-s3 instead.');
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

        throw AdapterException::filesystemFactoryNotFound($type);
    }

    /**
     * @param iterable<AdapterFactoryInterface> $adapterFactories
     */
    private function checkDuplicates(iterable $adapterFactories): void
    {
        $duplicates = [];
        foreach ($adapterFactories as $adapter) {
            $type = mb_strtolower($adapter->getType());
            if (\array_key_exists($type, $duplicates)) {
                throw AdapterException::duplicateFilesystemFactory($type);
            }

            $duplicates[$type] = true;
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
        // Change from use Request::createFromGlobals because files in $_FILES could be deleted
        $request = new Request(query: $_GET, server: $_SERVER);

        $basePath = $request->getSchemeAndHttpHost() . $request->getBasePath();
        $requestUrl = rtrim($basePath, '/') . '/';

        if ($request->getHost() === '' && EnvironmentHelper::getVariable('APP_URL')) {
            $requestUrl = (string) EnvironmentHelper::getVariable('APP_URL');
        }

        return $requestUrl;
    }
}
