<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Changelog\ChangelogParser;
use Shopware\Core\Framework\Plugin\Changelog\ChangelogService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;

trait PluginTestsHelper
{
    protected function createPluginService(
        EntityRepositoryInterface $pluginRepo,
        EntityRepositoryInterface $languageRepo,
        string $projectDir,
        PluginFinder $pluginFinder
    ): PluginService {
        return new PluginService(
            __DIR__ . '/_fixture/plugins',
            $projectDir,
            $pluginRepo,
            $languageRepo,
            new ChangelogService(new ChangelogParser()),
            $pluginFinder,
            new VersionSanitizer()
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
                    'name' => \SwagTest\SwagTest::class,
                    'version' => $version,
                    'label' => \SwagTest\SwagTest::PLUGIN_LABEL,
                    'installedAt' => $installedAt,
                    'active' => false,
                    'autoload' => [],
                ],
            ],
            $context
        );
    }
}
