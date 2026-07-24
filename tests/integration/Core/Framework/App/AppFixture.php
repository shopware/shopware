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
        $metadata = $manifest->getMetadata();
        $name = $metadata->getName();
        $labels = $metadata->getLabel();

        $data = [
            'name' => $name,
            'path' => $manifest->getPath(),
            'version' => $metadata->getVersion(),
            'label' => $labels['en-GB'] ?? reset($labels) ?: $name,
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
            $data['appSecret'] = $appSecret;
        }

        return $this->createAppFromData($data);
    }

    /**
     * Persist an app without a manifest. Pass any app fields to override the defaults.
     *
     * @param array<string, mixed> $data
     */
    public function createAppFromData(array $data = []): AppEntity
    {
        $id = Uuid::randomHex();

        $app = array_merge([
            'id' => $id,
            'name' => 'app-' . $id,
            'active' => true,
            'path' => '/apps/app-' . $id,
            'version' => '1.0.0',
            'label' => 'app-' . $id,
            'accessToken' => 'test',
            'integration' => [
                'label' => 'app-' . $id,
                'accessKey' => 'app-' . $id,
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'app-' . $id,
            ],
        ], $data);

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
            ->getEntities()->first();

        \assert($app instanceof AppEntity);

        return $app;
    }
}
