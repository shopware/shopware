<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationCollection;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Handler\ContentSystemBindingSpecificationLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\ContentSystemElementTypeLifecycleHandler;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\ContentSystem\StubExtractorEntity;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * Proves the app tier end-to-end through the real install path. `testInstallPersistsCanonicalInlineBinding`
 * covers the type-overlay resolution: the fixture app ships an element type with one inline binding on its own
 * type; only the binding handler runs here, the app's element types are never persisted, so the element-type
 * registry does not carry the type, and the binding therefore canonicalizes only because the persister overlays
 * the app's own types read from the filesystem, exactly the inactive-install condition the overlay exists to
 * cover. The other two tests cover the `resolvedBy` shorthand: one pins the install-gate failure
 * for a reference FQCN no registered entity produces, the other pins the synthesized default's DB round-trip.
 *
 * @internal
 */
#[Package('framework')]
class ContentSystemBindingSpecificationLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURE = __DIR__ . '/_fixtures/binding-specification-inline';

    private const RESOLVEDBY_UNREGISTERED_FIXTURE = __DIR__ . '/_fixtures/binding-specification-resolvedby-unregistered';

    private const RESOLVEDBY_DEFAULT_FIXTURE = __DIR__ . '/_fixtures/binding-specification-resolvedby-default';

    private ContentSystemBindingSpecificationLifecycleHandler $handler;

    private AppFixture $appFixture;

    private AppManager $appManager;

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

        $appManager = static::getContainer()->get(AppManager::class);
        static::assertInstanceOf(AppManager::class, $appManager);
        $this->appManager = $appManager;
    }

    #[TestDox('persists an inline binding as a canonical row: a {loader, config} resolves map and the derived required flag on the synthesized input')]
    public function testInstallPersistsCanonicalInlineBinding(): void
    {
        [, $bindings] = $this->install();
        $binding = $this->bindingByName($bindings, 'inline-media-binding');

        $schema = $binding->getSchema();

        static::assertSame('binding-specification-inline:MediaImage', $schema['type']);

        static::assertIsArray($schema['resolves']);
        // assertEquals, not assertSame: the schema round-trips through a MySQL `JSON` column, which normalizes
        // object member order (by key length, then bytewise) instead of preserving insertion order. Key order
        // carries no meaning for these maps, so only the key set and the values are asserted.
        static::assertEquals(
            ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $schema['resolves']['media'],
        );

        static::assertSame(['mediaId' => ['required' => true]], $schema['inputs']);
    }

    #[TestDox('fails install with the wrapped AppException when a resolvedBy FQCN derives to no registered entity, even with no authored bindings section')]
    public function testInstallFailsWhenResolvedByFqcnDerivesToNoRegisteredEntity(): void
    {
        try {
            $this->install(self::RESOLVEDBY_UNREGISTERED_FIXTURE);
            static::fail('Expected AppException was not thrown');
        } catch (AppException $e) {
            static::assertSame(AppException::CONTENT_SYSTEM_BINDING_SPECIFICATION_LOAD_FAILED, $e->getErrorCode());
            static::assertStringContainsString('Resources/content-system/types', $e->getMessage());
            static::assertStringContainsString(
                'Cannot canonicalize binding specification "binding-specification-resolvedby-unregistered:StubReference"',
                $e->getMessage(),
            );
            static::assertStringContainsString(
                \sprintf('no registered entity produces "%s" for reference "reference"', StubExtractorEntity::class),
                $e->getMessage(),
            );

            $previous = $e->getPrevious();
            static::assertInstanceOf(ContentSystemException::class, $previous);
            static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $previous->getErrorCode());
        }
    }

    #[TestDox('persists the resolvedBy-synthesized default as a canonical row, with no inputs, that round-trips unchanged through DatabaseBindingSpecificationLoader with default() true')]
    public function testInstallPersistsSynthesizedDefaultAndRoundTripsThroughDatabaseLoader(): void
    {
        $manifest = $this->appFixture->loadManifest(self::RESOLVEDBY_DEFAULT_FIXTURE . '/manifest.xml');
        $appName = $manifest->getMetadata()->getName();
        $expectedName = $appName . ':MediaImage';

        [$app, $bindings] = $this->install(self::RESOLVEDBY_DEFAULT_FIXTURE, installElementType: true);
        $binding = $this->bindingByName($bindings, $expectedName);

        static::assertSame($expectedName, $binding->getName());

        $schema = $binding->getSchema();
        static::assertSame($expectedName, $schema['type']);
        static::assertSame([], $schema['inputs']);

        // DatabaseBindingSpecificationLoader only reads active apps (WHERE a.active = 1); an installed app is
        // not active until the lifecycle's activation step runs, so activate it before reading through the loader.
        $this->appManager->activate($app, Context::createDefaultContext());

        $loader = static::getContainer()->get(DatabaseBindingSpecificationLoader::class);
        static::assertInstanceOf(DatabaseBindingSpecificationLoader::class, $loader);

        $specification = $this->specificationByQualifiedId($loader->load(), 'app:' . $appName . ':' . $expectedName);

        static::assertTrue($specification->isDefault());
        static::assertSame([], $specification->inputs());

        $resolves = $specification->resolves();
        static::assertArrayHasKey('media', $resolves);
        static::assertSame('entity', $resolves['media']->loader);
        // See the note in testInstallPersistsCanonicalInlineBinding: the config map comes back through the same
        // MySQL `JSON` column, so its key order is the storage engine's, not the authored one.
        static::assertEquals(['entity' => 'media', 'property' => 'mediaId'], $resolves['media']->config);
    }

    /**
     * $installElementType additionally runs the element-type lifecycle install on the same context, so the app's
     * own type is persisted to app_content_system_element_type: a synthesized default's type is that app's own
     * type, and DatabaseBindingSpecificationLoader re-validates each row against the live element-type registry
     * (empty overlay), so a caller that reads through it needs the type to actually be registered post-activation.
     *
     * @return array{AppEntity, AppContentSystemBindingSpecificationCollection}
     */
    private function install(string $fixtureDir = self::FIXTURE, bool $installElementType = false): array
    {
        $manifest = $this->appFixture->loadManifest($fixtureDir . '/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $context = $this->appFixture->createInstallContext($app, $manifest, new Filesystem($manifest->getPath()));
        $this->handler->install($context);

        if ($installElementType) {
            $elementTypeHandler = static::getContainer()->get(ContentSystemElementTypeLifecycleHandler::class);
            static::assertInstanceOf(ContentSystemElementTypeLifecycleHandler::class, $elementTypeHandler);
            $elementTypeHandler->install($context);
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('appId', $app->getId()));
        $bindings = $this->bindingRepository->search($criteria, Context::createDefaultContext())->getEntities();

        return [$app, $bindings];
    }

    /**
     * @param list<BindingSpecification> $specifications
     */
    private function specificationByQualifiedId(array $specifications, string $qualifiedId): BindingSpecification
    {
        foreach ($specifications as $specification) {
            if ($specification->qualifiedId() === $qualifiedId) {
                return $specification;
            }
        }

        static::fail(\sprintf('Binding specification "%s" was not returned by DatabaseBindingSpecificationLoader', $qualifiedId));
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
