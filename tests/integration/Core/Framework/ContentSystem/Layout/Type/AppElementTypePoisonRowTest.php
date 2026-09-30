<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Type;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AppElementTypePoisonRowTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function setUp(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $appId = $ids->get('app');
        $appName = 'AcmeElementTypePoison' . $ids->get('appNameSuffix');

        $this->appRepository()->create([[
            'id' => $appId,
            'name' => $appName,
            'path' => 'AcmeElementTypePoison',
            'version' => '1.0.0',
            'label' => 'Acme Element Type Poison',
            'active' => true,
            'integration' => ['label' => $appName, 'accessKey' => 'element-type-poison-' . $appId, 'secretAccessKey' => 'element-type-poison-' . $appId],
            'aclRole' => ['name' => $appName],
        ]], $context);

        $this->elementTypeRepository()->create([[
            'id' => $ids->get('element-type'),
            'appId' => $appId,
            'name' => $appName . ':Poison',
            'schema' => ['meta' => ['label' => '', 'description' => 'Poison element type']],
            'hash' => 'poison-hash',
        ]], $context);

        $this->registry()->invalidate();
    }

    protected function tearDown(): void
    {
        $this->registry()->invalidate();
    }

    #[TestDox('aborts registry construction when an active app has an invalid persisted element type')]
    public function testInvalidActiveAppRowAbortsRegistryConstruction(): void
    {
        try {
            $this->registry()->all();
            static::fail('Expected the invalid active-app element type row to abort registry construction.');
        } catch (ContentSystemException $exception) {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame(ContentSystemException::ELEMENT_TYPE_LOAD_FAILED, $exception->getErrorCode());
            static::assertStringContainsString('Poison', $exception->getMessage());
        }
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $registry = $this->getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $registry);

        return $registry;
    }

    /**
     * @return EntityRepository<AppCollection>
     */
    private function appRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('app.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<AppContentSystemElementTypeCollection>
     */
    private function elementTypeRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('app_content_system_element_type.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
