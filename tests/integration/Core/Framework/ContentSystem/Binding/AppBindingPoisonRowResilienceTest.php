<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * A persisted, active-app binding row that fails {@see TypeConsistentBindingSpecification} must not take
 * down the registry: {@see DatabaseBindingSpecificationLoader} skips and logs it, so the registry still
 * builds, introspection still serves the valid catalog, and a write attributed to a valid binding still
 * succeeds.
 *
 * @internal
 */
#[Package('framework')]
class AppBindingPoisonRowResilienceTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const CORE_MEDIA_BINDING_ID = 'core:from-media-library';
    private const POISON_BINDING_NAME = 'poison-binding';
    private const DOMAIN_LOADER_POISON_BINDING_NAME = 'domain-loader-poison-binding';

    private string $appName;

    protected function setUp(): void
    {
        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();
        $this->appName = 'AcmePoison' . Uuid::randomHex();

        $this->appRepository()->create([[
            'id' => $appId,
            'name' => $this->appName,
            'path' => 'AcmePoison',
            'version' => '1.0.0',
            'label' => 'Acme Poison',
            'active' => true,
            'integration' => [
                'label' => $this->appName,
                'accessKey' => 'poison-' . $appId,
                'secretAccessKey' => 'poison-' . $appId,
            ],
            'aclRole' => [
                'name' => $this->appName,
            ],
        ]], $context);

        // The persisted schema column is the DTO's normalized shape without the id; the row's "name"
        // column is the binding id (see DatabaseBindingSpecificationLoader).
        $poison = new BindingSpecificationDto(type: 'Sw:Does:NotExist', label: 'Poison', resolves: [], inputs: []);

        $this->bindingSpecificationRepository()->create([[
            'id' => Uuid::randomHex(),
            'appId' => $appId,
            'name' => self::POISON_BINDING_NAME,
            'schema' => (new BindingSpecificationSerializer())->normalize($poison),
            'hash' => 'poison-hash',
        ]], $context);

        // The registry caches its resolved set; force a rebuild so this test's poison row is actually
        // seen by the database loader on the next all() call.
        $this->registry()->invalidate();
    }

    protected function tearDown(): void
    {
        $this->registry()->invalidate();
    }

    #[TestDox('builds the registry around a persisted, active-app binding whose declared type is not registered, keeping the valid core binding and omitting only the poison one')]
    public function testRegistryStillBuildsWhenPersistedAppBindingDeclaresUnregisteredType(): void
    {
        $all = $this->registry()->all();

        static::assertArrayNotHasKey('app:' . $this->appName . ':' . self::POISON_BINDING_NAME, $all);
        static::assertArrayHasKey(self::CORE_MEDIA_BINDING_ID, $all);
    }

    #[TestDox('builds the registry around a persisted, active-app binding whose resolves entry uses a domain loader with a config the domain serializer rejects, keeping the valid core binding and omitting only the poison one')]
    public function testRegistryStillBuildsWhenPersistedAppBindingHasMalformedDomainLoaderConfig(): void
    {
        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();
        $appName = 'AcmeDomainLoaderPoison' . Uuid::randomHex();

        $this->appRepository()->create([[
            'id' => $appId,
            'name' => $appName,
            'path' => 'AcmeDomainLoaderPoison',
            'version' => '1.0.0',
            'label' => 'Acme Domain Loader Poison',
            'active' => true,
            'integration' => [
                'label' => $appName,
                'accessKey' => 'domain-loader-poison-' . $appId,
                'secretAccessKey' => 'domain-loader-poison-' . $appId,
            ],
            'aclRole' => [
                'name' => $appName,
            ],
        ]], $context);

        // "Sw:Product:Listing"'s "listing" property is a reference property the product_listing loader can
        // fill; "associations" must be an array, so this config decodes fine structurally but is rejected by
        // ProductListingLoaderConfigSerializer::decode() -- a domain exception the SF1 fix reclassifies to
        // ContentSystemException at the shared decode chokepoint, which TypeConsistentBindingSpecificationValidator
        // then turns into a load-time violation instead of an uncaught exception.
        $poison = new BindingSpecificationDto(
            type: 'Sw:Product:Listing',
            label: 'Domain Loader Poison',
            resolves: [
                'listing' => ['loader' => 'product_listing', 'config' => ['associations' => 'not-an-array']],
            ],
            inputs: [],
        );

        $this->bindingSpecificationRepository()->create([[
            'id' => Uuid::randomHex(),
            'appId' => $appId,
            'name' => self::DOMAIN_LOADER_POISON_BINDING_NAME,
            'schema' => (new BindingSpecificationSerializer())->normalize($poison),
            'hash' => 'domain-loader-poison-hash',
        ]], $context);

        $this->registry()->invalidate();

        $all = $this->registry()->all();

        static::assertArrayNotHasKey('app:' . $appName . ':' . self::DOMAIN_LOADER_POISON_BINDING_NAME, $all);
        static::assertArrayHasKey(self::CORE_MEDIA_BINDING_ID, $all);
    }

    #[TestDox('serves the introspection endpoint listing the valid core binding while the poison app binding is persisted')]
    public function testIntrospectionEndpointListsValidBindingAndOmitsPoisonBinding(): void
    {
        $this->getBrowser()->request('GET', '/api/_info/content-system-binding-specifications.json');
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertIsArray($data['bindingSpecifications']);
        static::assertArrayHasKey(self::CORE_MEDIA_BINDING_ID, $data['bindingSpecifications']);
        static::assertArrayNotHasKey('app:' . $this->appName . ':' . self::POISON_BINDING_NAME, $data['bindingSpecifications']);
    }

    #[TestDox('persists a content_layout write attributed to the valid core binding while the poison app binding row is present')]
    public function testValidBindingWriteSucceedsWithPoisonAppBindingRowPresent(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $elementId = Uuid::randomHex();

        $this->contentLayoutRepository()->create([[
            'id' => $layoutId,
            'name' => 'poison-resilience-valid-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$this->boundImageElement($elementId)],
        ]], $context);

        static::assertSame(
            ['media' => self::CORE_MEDIA_BINDING_ID],
            $this->reload($layoutId)->getLayout()[0]->getAttributedSpecifications()
        );
    }

    #[TestDox('drops a stale attribution to a non-resolving id while its wiring stays intact, on a content_layout write made with the poison app binding row present')]
    public function testStaleAttributionDropsWhileWiringStaysIntactWithPoisonAppBindingRowPresent(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $elementId = Uuid::randomHex();

        $this->contentLayoutRepository()->create([[
            'id' => $layoutId,
            'name' => 'poison-resilience-stale-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$this->boundImageElement($elementId, 'app:GhostApp:removed-binding')],
        ]], $context);

        $reconciled = $this->reload($layoutId)->getLayout()[0];
        static::assertSame([], $reconciled->getAttributedSpecifications());

        $requirement = $reconciled->getDataRequirements()['media'];
        static::assertInstanceOf(EntityLoaderConfig::class, $requirement->config);
        static::assertSame('media', $requirement->config->entity);
        static::assertSame('mediaId', $requirement->config->property);
    }

    /**
     * A Sw:Media:Image element wired to core:from-media-library's media requirement, with mediaId
     * always filled so the element stays resolvable and the write is never rejected by the
     * resolvability gate. $specificationId lets a caller attribute the (still-matching) wiring to a
     * specification id that does not resolve from the registry.
     *
     * @return array<string, mixed>
     */
    private function boundImageElement(string $id, string $specificationId = self::CORE_MEDIA_BINDING_ID): array
    {
        return [
            'id' => $id,
            'component' => 'Sw:Media:Image',
            'properties' => ['mediaId' => 'a-media-id'],
            'dataRequirements' => [
                'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            ],
            'attributedSpecifications' => ['media' => $specificationId],
        ];
    }

    private function reload(string $layoutId): ContentLayoutEntity
    {
        $layout = $this->contentLayoutRepository()->search(new Criteria([$layoutId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        return $layout;
    }

    private function registry(): AbstractContentSystemBindingSpecificationRegistry
    {
        $registry = $this->getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        return $registry;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function contentLayoutRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
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
