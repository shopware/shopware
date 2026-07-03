<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture as UnitAppFixture;

/**
 * Helpers for testing app lifecycle components in integration tests
 *
 * @internal
 */
final class AppFixture
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(private readonly EntityRepository $appRepository)
    {
    }

    public function loadManifest(string $manifestPath): Manifest
    {
        return Manifest::createFromXmlFile($manifestPath);
    }

    public function createApp(Manifest $manifest, ?string $appSecret = 's3cr3t'): AppEntity
    {
        $id = Uuid::randomHex();
        $metadata = $manifest->getMetadata();
        $name = $metadata->getName();
        $labels = $metadata->getLabel();
        $label = $labels['en-GB'] ?? reset($labels) ?: $name;

        $app = [
            'id' => $id,
            'name' => $name,
            'active' => true,
            'path' => $manifest->getPath(),
            'version' => $metadata->getVersion(),
            'label' => $label,
            'accessToken' => 'test',
            'integration' => [
                'label' => $name,
                'accessKey' => $name,
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => $name,
            ],
        ];

        if ($appSecret !== null) {
            $app['appSecret'] = $appSecret;
        }

        $this->appRepository->create([$app], Context::createDefaultContext());

        return $this->getApp($id);
    }

    public function createInstallContext(
        AppEntity $app,
        Manifest $manifest,
        ?Filesystem $appFilesystem = null,
        string $defaultLocale = 'en-GB'
    ): AppPersistContext {
        return UnitAppFixture::createInstallContext($app, $manifest, $appFilesystem, $defaultLocale);
    }

    public function createUpdateContext(
        AppEntity $app,
        Manifest $manifest,
        ?Filesystem $appFilesystem = null,
        string $defaultLocale = 'en-GB'
    ): AppPersistContext {
        return UnitAppFixture::createUpdateContext($app, $manifest, $appFilesystem, $defaultLocale);
    }

    public function getApp(string $appId): AppEntity
    {
        $app = $this->appRepository
            ->search(new Criteria([$appId]), Context::createDefaultContext())
            ->first();

        \assert($app instanceof AppEntity);

        return $app;
    }
}
