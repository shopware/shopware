<?php declare(strict_types=1);

namespace SwagTest;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Test\Plugin\_fixture\bundles\FooBarBundle;
use Shopware\Core\Framework\Test\Plugin\_fixture\bundles\GizmoBundle;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SwagTest extends Plugin
{
    public const PLUGIN_LABEL = 'English plugin name';

    public const PLUGIN_VERSION = '1.0.1';

    public const PLUGIN_OLD_VERSION = '1.0.0';

    public const PLUGIN_GERMAN_LABEL = 'Deutscher Pluginname';

    public const THROW_ERROR_ON_UPDATE = 'throw-error-on-update';
    public const THROW_ERROR_ON_DEACTIVATE = 'throw-error-on-deactivate';

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
    }

    public function update(UpdateContext $updateContext): void
    {
        if ($updateContext->getContext()->hasExtension(self::THROW_ERROR_ON_UPDATE)) {
            throw new \BadMethodCallException('Update throws an error');
        }

        parent::update($updateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        if ($deactivateContext->getContext()->hasExtension(self::THROW_ERROR_ON_DEACTIVATE)) {
            throw new \BadFunctionCallException('Deactivate throws an error');
        }
        parent::deactivate($deactivateContext);
    }

    public function getMigrationNamespace(): string
    {
        return $_SERVER['FAKE_MIGRATION_NAMESPACE'] ?? parent::getMigrationNamespace();
    }

    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        require_once __DIR__ . '/../../../bundles/FooBarBundle.php';
        require_once __DIR__ . '/../../../bundles/GizmoBundle.php';

        return [
            new FooBarBundle(),
            -10 => new GizmoBundle(),
        ];
    }
}
