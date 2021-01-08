<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Symfony\Component\Filesystem\Filesystem;

trait ExtensionBehaviour
{
    public function installApp(string $path, bool $install = true): void
    {
        $appRepository = $this->getContainer()->get('app.repository');
        $idResult = $appRepository->searchIds(new Criteria(), Context::createDefaultContext());

        if (\count($idResult->getIds())) {
            $appRepository->delete(array_map(function (string $id) {
                return ['id' => $id];
            }, $idResult->getIds()), Context::createDefaultContext());
        }

        $fs = new Filesystem();

        $name = basename($path);
        $appDir = $this->getContainer()->getParameter('shopware.app_dir') . '/' . $name;
        $fs->mirror($path, $appDir);

        if ($install) {
            $this->getContainer()->get(AbstractStoreAppLifecycleService::class)->installExtension($name, Context::createDefaultContext());
        }
    }

    public function removeApp(string $path): void
    {
        $fs = new Filesystem();

        $fs->remove($this->getContainer()->getParameter('shopware.app_dir') . '/' . basename($path));
    }

    public function registerPlugin(string $path): void
    {
        $fs = new Filesystem();

        $name = basename($path);
        $pluginDir = $this->getContainer()->getParameter('kernel.plugin_dir') . '/' . $name;
        $fs->mirror($path, $pluginDir);
    }

    public function removePlugin(string $path): void
    {
        $fs = new Filesystem();

        $fs->remove($this->getContainer()->getParameter('kernel.plugin_dir') . '/' . basename($path));
    }
}
