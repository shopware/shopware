<?php declare(strict_types=1);

namespace SwagTest;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SwagTest extends Plugin
{
    public const PLUGIN_LABEL = 'English plugin name';

    public const PLUGIN_VERSION = '1.0.1';

    public const PLUGIN_OLD_VERSION = '1.0.0';

    public const PLUGIN_GERMAN_LABEL = 'Deutscher Pluginname';

    /**
     * @var SystemConfigService
     */
    public $systemConfig;

    /**
     * @var EntityRepositoryInterface
     */
    public $categoryRepository;

    /**
     * @var Plugin\Context\ActivateContext|null
     */
    public $preActivateContext;

    /**
     * @var Plugin\Context\ActivateContext|null
     */
    public $postActivateContext;

    /**
     * @var Plugin\Context\DeactivateContext|null
     */
    public $preDeactivateContext;

    /**
     * @var Plugin\Context\DeactivateContext|null
     */
    public $postDeactivateContext;

    /**
     * @required
     */
    public function requiredSetterOfPrivateService(SystemConfigService $systemConfig): void
    {
        $this->systemConfig = $systemConfig;
    }

    public function manualSetter(EntityRepositoryInterface $categoryRepository): void
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if (isset($_SERVER['TEST_KEEP_MIGRATIONS'])) {
            $uninstallContext->enableKeepMigrations();
        }
    }

    public function getMigrationNamespace(): string
    {
        return $_SERVER['FAKE_MIGRATION_NAMESPACE'] ?? parent::getMigrationNamespace();
    }
}
