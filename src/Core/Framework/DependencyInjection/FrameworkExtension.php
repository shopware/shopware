<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\PasswordHasher\NativePasswordHasher;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

#[Package('framework')]
class FrameworkExtension extends Extension
{
    private const ALIAS = 'shopware';

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return self::ALIAS;
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $this->addShopwareConfig($container, $this->getAlias(), $config);
        $this->addPasswordHasherPlaceholderHashes($container, $config['password_hasher']);
    }

    /**
     * Creates the hash each password hasher verifies when there is no stored hash, e.g. for an unknown email.
     * It is created once with the configured algorithm and options, so verifying it takes as long as verifying a customer's or user's hash.
     * Settings from environment variables are only known at runtime, the hasher then hashes the given password instead.
     *
     * @param array<string, array{algorithm: string, options: array<string, mixed>}> $config
     */
    private function addPasswordHasherPlaceholderHashes(ContainerBuilder $container, array $config): void
    {
        foreach ($config as $scope => $scopeConfig) {
            $placeholderHash = null;

            if (self::isResolvedPasswordHasherConfig($scopeConfig)) {
                $placeholderHash = (new NativePasswordHasher($scopeConfig['algorithm'], $scopeConfig['options']))->hash(bin2hex(random_bytes(16)));
            }

            $container->setParameter('shopware.password_hasher.' . $scope . '.placeholder_hash', $placeholderHash);
        }
    }

    /**
     * @param array{algorithm: string, options: array<string, mixed>} $scopeConfig
     *
     * @phpstan-assert-if-true array{algorithm: string, options: array<string, int>} $scopeConfig
     */
    private static function isResolvedPasswordHasherConfig(array $scopeConfig): bool
    {
        if (!\in_array($scopeConfig['algorithm'], NativePasswordHasher::ALGORITHMS, true)) {
            return false;
        }

        foreach ($scopeConfig['options'] as $option) {
            if (!\is_int($option)) {
                return false;
            }
        }

        return true;
    }

    private function addShopwareConfig(ContainerBuilder $container, string $alias, array $options): void
    {
        foreach ($options as $key => $option) {
            $key = $alias . '.' . $key;
            $container->setParameter($key, $option);

            /*
             * The route cache in dev mode checks on each request if its fresh. If you use the following expression
             * `defaults={"auth_required"="%shopware.api.api_browser.auth_required%"}` it also checks if the parameter
             * matches the value in the container. The expression always results in a string, but the value in the
             * container is a boolean. So they never match. To workaround this, we add this as an additional string
             * parameter. So in the dynamic use case you have to use `defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}`
             */
            if ($key === 'shopware.api.api_browser.auth_required') {
                $container->setParameter('shopware.api.api_browser.auth_required_str', (string) (int) $option);
            }

            if (\is_array($option)) {
                $this->addShopwareConfig($container, $key, $option);
            }
        }
    }
}
