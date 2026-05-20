<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Framework\App\AppFixtureTestBehaviour as UnitAppFixtureTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helpers for testing app lifecycle components in integrations tests
 */
trait AppFixtureTestBehaviour
{
    use UnitAppFixtureTestBehaviour;

    abstract protected static function getContainer(): ContainerInterface;

    protected function loadManifest(string $manifestPath): Manifest
    {
        return Manifest::createFromXmlFile($manifestPath);
    }

    protected function createApp(Manifest $manifest): AppEntity
    {
        $id = Uuid::randomHex();
        $metadata = $manifest->getMetadata();
        $name = $metadata->getName();
        $labels = $metadata->getLabel();
        $label = $labels['en-GB'] ?? reset($labels) ?: $name;

        $this->appRepository()->create([[
            'id' => $id,
            'name' => $name,
            'active' => true,
            'path' => $manifest->getPath(),
            'version' => $metadata->getVersion(),
            'label' => $label,
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => $name,
                'accessKey' => $name,
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => $name,
            ],
        ]], Context::createDefaultContext());

        return $this->getApp($id);
    }

    protected function getApp(string $appId): AppEntity
    {
        $app = $this->appRepository()->search(new Criteria([$appId]), Context::createDefaultContext())->first();

        static::assertInstanceOf(AppEntity::class, $app);

        return $app;
    }

    /**
     * @return EntityRepository<AppCollection>
     */
    private function appRepository(): EntityRepository
    {
        /** @var EntityRepository<AppCollection> $repository */
        $repository = static::getContainer()->get('app.repository');

        return $repository;
    }
}
