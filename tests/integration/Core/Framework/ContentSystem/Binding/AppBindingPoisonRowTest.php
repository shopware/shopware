<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * An invalid persisted row belonging to an active app must abort registry construction.
 *
 * @internal
 */
#[Package('framework')]
class AppBindingPoisonRowTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function setUp(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $appId = $ids->get('app');
        $appName = 'AcmePoison' . $ids->get('appNameSuffix');

        $this->appRepository()->create([[
            'id' => $appId,
            'name' => $appName,
            'path' => 'AcmePoison',
            'version' => '1.0.0',
            'label' => 'Acme Poison',
            'active' => true,
            'integration' => ['label' => $appName, 'accessKey' => 'poison-' . $appId, 'secretAccessKey' => 'poison-' . $appId],
            'aclRole' => ['name' => $appName],
        ]], $context);

        $poison = new BindingSpecificationDto(type: 'Sw:Does:NotExist', label: 'Poison', resolves: [], inputs: []);
        $this->bindingSpecificationRepository()->create([[
            'id' => $ids->get('binding'),
            'appId' => $appId,
            'name' => 'poison-binding',
            'schema' => (new BindingSpecificationSerializer())->normalize($poison),
            'hash' => 'poison-hash',
        ]], $context);

        $this->registry()->invalidate();
    }

    protected function tearDown(): void
    {
        $this->registry()->invalidate();
    }

    #[TestDox('aborts registry construction when an active app has an invalid persisted binding')]
    public function testInvalidActiveAppRowAbortsRegistryConstruction(): void
    {
        try {
            $this->registry()->all();
            static::fail('Expected the invalid active-app binding row to abort registry construction.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::BINDING_SPECIFICATIONS_INVALID, $exception->getErrorCode());
            static::assertStringContainsString('poison-binding', $exception->getMessage());
        }
    }

    private function registry(): AbstractContentSystemBindingSpecificationRegistry
    {
        $registry = $this->getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

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
     * @return EntityRepository<AppContentSystemBindingSpecificationCollection>
     */
    private function bindingSpecificationRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('app_content_system_binding_specification.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
