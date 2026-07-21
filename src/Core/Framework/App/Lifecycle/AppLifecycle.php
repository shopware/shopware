<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
class AppLifecycle extends AbstractAppLifecycle
{
    public function __construct(
        private readonly AppManager $appManager,
        private readonly AppStorage $appStorage,
    ) {
    }

    public function getDecorated(): AbstractAppLifecycle
    {
        throw new DecorationPatternException(self::class);
    }

    public function install(Manifest $manifest, AppInstallParameters $parameters, Context $context): void
    {
        $this->appManager->install($manifest, $parameters, $context);
    }

    public function activate(string $appId, Context $context): void
    {
        $this->appManager->activate($this->loadApp($appId, $context), $context);
    }

    public function deactivate(string $appId, Context $context): void
    {
        $this->appManager->deactivate($this->loadApp($appId, $context), $context);
    }

    public function update(Manifest $manifest, AppUpdateParameters $parameters, array $app, Context $context): void
    {
        $this->appManager->update($manifest, $parameters, $this->loadApp($app['id'], $context), $context);
    }

    public function uninstall(string $appName, array $app, Context $context, bool $keepUserData = false): void
    {
        $this->appManager->uninstall($this->loadApp($app['id'], $context), $context, $keepUserData);
    }

    private function loadApp(string $id, Context $context): AppEntity
    {
        $app = $this->appStorage->findById($id, $context);
        if ($app === null) {
            throw AppException::notFound($id);
        }

        return $app;
    }
}
