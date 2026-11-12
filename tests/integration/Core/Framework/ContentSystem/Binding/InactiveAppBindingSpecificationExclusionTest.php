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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Covers the end-to-end counterpart to DatabaseBindingSpecificationLoaderTest's mocked
 * "WHERE a.active = 1" SQL-substring assertion: a binding persisted for an inactive app must not
 * appear in {@see AbstractContentSystemBindingSpecificationRegistry::all()} once the registry is
 * rebuilt against the real database, while a sibling binding persisted for an active app -- and the
 * core "Sw:Media:Image" synthesized default shipped via the filesystem loader -- both remain, proving
 * the exclusion is not simply an empty registry.
 *
 * @internal
 */
#[Package('framework')]
class InactiveAppBindingSpecificationExclusionTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const CORE_MEDIA_BINDING_ID = 'core:Sw:Media:Image';
    private const ACTIVE_BINDING_NAME = 'active-app-binding';
    private const INACTIVE_BINDING_NAME = 'inactive-app-binding';

    private string $activeAppName;

    private string $inactiveAppName;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $context = Context::createDefaultContext();

        $this->activeAppName = 'AcmeActive' . $this->ids->get('activeAppName');
        $activeAppId = $this->createApp($this->activeAppName, true);
        $this->createBinding($activeAppId, self::ACTIVE_BINDING_NAME, $this->activeAppName, $context);

        $this->inactiveAppName = 'AcmeInactive' . $this->ids->get('inactiveAppName');
        $inactiveAppId = $this->createApp($this->inactiveAppName, false);
        $this->createBinding($inactiveAppId, self::INACTIVE_BINDING_NAME, $this->inactiveAppName, $context);

        // The registry caches its resolved set; force a rebuild so this test's rows are actually seen
        // by the database loader on the next all() call.
        $this->registry()->invalidate();
    }

    protected function tearDown(): void
    {
        $this->registry()->invalidate();
    }

    #[TestDox('excludes an inactive app binding from all() while an active app binding and the core binding remain present')]
    public function testAllExcludesInactiveAppBindingWhileKeepingActiveAppAndCoreBindings(): void
    {
        $all = $this->registry()->all();

        static::assertArrayNotHasKey('app:' . $this->inactiveAppName . ':' . self::INACTIVE_BINDING_NAME, $all);
        static::assertArrayHasKey('app:' . $this->activeAppName . ':' . self::ACTIVE_BINDING_NAME, $all);
        static::assertArrayHasKey(self::CORE_MEDIA_BINDING_ID, $all);
    }

    private function createApp(string $appName, bool $active): string
    {
        $appId = $this->ids->get($active ? 'activeAppId' : 'inactiveAppId');

        $this->appRepository()->create([[
            'id' => $appId,
            'name' => $appName,
            'path' => $appName,
            'version' => '1.0.0',
            'label' => $appName,
            'active' => $active,
            'integration' => [
                'label' => $appName,
                'accessKey' => 'access-' . $appId,
                'secretAccessKey' => 'secret-' . $appId,
            ],
            'aclRole' => [
                'name' => $appName,
            ],
        ]], Context::createDefaultContext());

        return $appId;
    }

    private function createBinding(string $appId, string $bindingName, string $appName, Context $context): void
    {
        // Reuses the shape of the shipped core:Sw:Media:Image default so TypeConsistentBindingSpecificationValidator
        // (run for real against the live element-type registry in this integration test) accepts it: the wiring
        // targets the reference's undeclared resolvedBy storage key and carries no inputs facet.
        $dto = new BindingSpecificationDto(
            type: 'Sw:Media:Image',
            label: 'Binding for ' . $appName,
            resolves: [
                'media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            ],
            inputs: [],
        );

        $this->bindingSpecificationRepository()->create([[
            'id' => $this->ids->get('binding-' . $bindingName),
            'appId' => $appId,
            'name' => $bindingName,
            'schema' => (new BindingSpecificationSerializer())->normalize($dto),
            'hash' => 'hash-' . $bindingName,
        ]], $context);
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
