<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationCollection;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationEntity;
use Shopware\Core\Framework\App\Lifecycle\Handler\ContentSystemBindingSpecificationLifecycleHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * Proves the type-overlay resolution for the app tier end-to-end: the fixture app ships an element type with one
 * inline binding on that own type. Only the binding handler runs here; the app's element types are never
 * persisted, so the element-type registry does not carry the type. The binding therefore canonicalizes only
 * because the persister overlays the app's own types read from the filesystem, exactly the inactive-install
 * condition the overlay exists to cover.
 *
 * @internal
 */
class ContentSystemBindingSpecificationLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURE = __DIR__ . '/_fixtures/binding-specification-inline';

    private ContentSystemBindingSpecificationLifecycleHandler $handler;

    private AppFixture $appFixture;

    /**
     * @var EntityRepository<AppContentSystemBindingSpecificationCollection>
     */
    private EntityRepository $bindingRepository;

    protected function setUp(): void
    {
        $handler = static::getContainer()->get(ContentSystemBindingSpecificationLifecycleHandler::class);
        static::assertInstanceOf(ContentSystemBindingSpecificationLifecycleHandler::class, $handler);
        $this->handler = $handler;

        $bindingRepository = static::getContainer()->get('app_content_system_binding_specification.repository');
        static::assertInstanceOf(EntityRepository::class, $bindingRepository);
        $this->bindingRepository = $bindingRepository;

        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
    }

    #[TestDox('persists an inline binding as a canonical row: a {loader, config} resolves map and the derived required flag on the synthesized input')]
    public function testInstallPersistsCanonicalInlineBinding(): void
    {
        $binding = $this->bindingByName($this->install(), 'inline-media-binding');

        $schema = $binding->getSchema();

        static::assertSame('binding-specification-inline:MediaImage', $schema['type']);

        static::assertIsArray($schema['resolves']);
        static::assertSame(
            ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $schema['resolves']['media'],
        );

        static::assertSame(['mediaId' => ['required' => true]], $schema['inputs']);
    }

    private function install(): AppContentSystemBindingSpecificationCollection
    {
        $manifest = $this->appFixture->loadManifest(self::FIXTURE . '/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $context = $this->appFixture->createInstallContext($app, $manifest, new Filesystem($manifest->getPath()));
        $this->handler->install($context);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('appId', $app->getId()));

        return $this->bindingRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    private function bindingByName(AppContentSystemBindingSpecificationCollection $bindings, string $name): AppContentSystemBindingSpecificationEntity
    {
        foreach ($bindings as $binding) {
            if ($binding->getName() === $name) {
                return $binding;
            }
        }

        static::fail(\sprintf('Binding "%s" was not persisted', $name));
    }
}
