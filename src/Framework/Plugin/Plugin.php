<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Framework\Plugin;

use Shopware\Filesystem\PrefixFilesystem;
use Shopware\Framework\Plugin\Context\ActivateContext;
use Shopware\Framework\Plugin\Context\DeactivateContext;
use Shopware\Framework\Plugin\Context\InstallContext;
use Shopware\Framework\Plugin\Context\UninstallContext;
use Shopware\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class Plugin extends Bundle
{
    /**
     * @var bool
     */
    private $active;

    public function __construct(bool $active = true)
    {
        $this->active = $active;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param string $environment (dev, prod, test)
     *
     * @return BundleInterface[]
     */
    public function registerBundles(string $environment): array
    {
        return [];
    }

    /**
     * This method can be overridden
     *
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
    }

    /**
     * This method can be overridden
     *
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    /**
     * This method can be overridden
     *
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    /**
     * This method can be overridden
     *
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    /**
     * This method can be overridden
     *
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    public function build(ContainerBuilder $container)
    {
        $this->registerFilesystem($container, 'private');
        $this->registerFilesystem($container, 'public');
    }

    public function getContainerPrefix(): string
    {
        return $this->camelCaseToUnderscore($this->getName());
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $key
     */
    private function registerFilesystem(ContainerBuilder $container, string $key)
    {
        $parameterKey = sprintf('shopware.filesystem.%s', $key);
        $serviceId = sprintf('%s.filesystem.%s', $this->getContainerPrefix(), $key);

        $filesystem = new Definition(
            PrefixFilesystem::class,
            [
                new Reference($parameterKey),
                'plugins/' . $this->getContainerPrefix(),
            ]
        );

        $container->setDefinition($serviceId, $filesystem);
    }

    private function camelCaseToUnderscore(string $string): string
    {
        return strtolower(ltrim(preg_replace('/[A-Z]/', '_$0', $string), '_'));
    }
}
