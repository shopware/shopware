<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store;

use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
trait ServiceBehaviour
{
    use ExtensionBehaviour;

    public function installService(string $path, bool $install = true): void
    {
        $appRepository = static::getContainer()->get('app.repository');
        $idResult = $appRepository->searchIds(new Criteria(), Context::createDefaultContext());

        /** @var array<string> $ids */
        $ids = $idResult->getIds();
        if (\count($ids)) {
            $appRepository->delete(array_map(fn (string $id) => ['id' => $id], $ids), Context::createDefaultContext());
        }

        $fs = new Filesystem();

        $name = basename($path);
        $appDir = static::getContainer()->getParameter('shopware.app_dir') . '/' . $name;
        $fs->mirror($path, $appDir);

        $manifest = Manifest::createFromXmlFile($appDir . '/manifest.xml');
        $manifest->getMetadata()->setSelfManaged(true);

        if ($install) {
            static::getContainer()->get(AppLifecycle::class)->install($manifest, true, Context::createDefaultContext());
        }
    }
}
