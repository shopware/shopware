<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Changelog\ChangelogParser;
use Shopware\Core\Framework\Plugin\Changelog\ChangelogService;
use Shopware\Core\Framework\Plugin\Helper\ComposerPackageProvider;
use Shopware\Core\Framework\Plugin\PluginService;

trait PluginTestsHelper
{
    protected function createPluginService(
        EntityRepositoryInterface $pluginRepo,
        EntityRepositoryInterface $languageRepo
    ): PluginService {
        return new PluginService(
            __DIR__ . '/_fixture',
            $pluginRepo,
            $languageRepo,
            new ComposerPackageProvider(),
            new ChangelogService(new ChangelogParser())
        );
    }

    protected function createPlugin(
        EntityRepositoryInterface $pluginRepo,
        Context $context,
        string $version = \SwagTest\SwagTest::PLUGIN_VERSION,
        ?string $installedAt = null
    ): void {
        $pluginRepo->create(
            [
                [
                    'name' => \SwagTest\SwagTest::PLUGIN_NAME,
                    'version' => $version,
                    'label' => \SwagTest\SwagTest::PLUGIN_LABEL,
                    'installedAt' => $installedAt,
                ],
            ],
            $context
        );
    }
}
