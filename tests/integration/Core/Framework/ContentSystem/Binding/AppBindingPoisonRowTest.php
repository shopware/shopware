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
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * An invalid persisted row belonging to an active app must abort registry construction.
 *
 * @internal
 */
#[Package('framework')]
class AppBindingPoisonRowTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const CORE_MEDIA_BINDING_ID = 'core:Sw:Media:Image';

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
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame(ContentSystemException::BINDING_SPECIFICATION_LOAD_FAILED, $exception->getErrorCode());
            static::assertStringContainsString('poison-binding', $exception->getMessage());
        }
    }

    #[TestDox('rejects an otherwise valid content layout write when an active app has an invalid persisted binding')]
    public function testValidBindingWriteFailsWithPoisonAppBindingRowPresent(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $layoutId = $ids->get('layout');

        try {
            $this->contentLayoutRepository()->create([[
                'id' => $layoutId,
                'name' => 'poison-row-write-' . $layoutId,
                'version' => '1.0.0',
                'rootSource' => 'none',
                'layout' => [[
                    'id' => $ids->get('element'),
                    'component' => 'Sw:Media:Image',
                    'properties' => ['mediaId' => 'a-media-id'],
                    'dataRequirements' => [
                        'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
                    ],
                    'attributedSpecifications' => ['media' => self::CORE_MEDIA_BINDING_ID],
                ]],
            ]], $context);
            static::fail('Expected the invalid active-app binding row to reject the content layout write.');
        } catch (ContentSystemException $exception) {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame(ContentSystemException::BINDING_SPECIFICATION_LOAD_FAILED, $exception->getErrorCode());
            static::assertStringContainsString('poison-binding', $exception->getMessage());
        }

        static::assertNull(
            $this->contentLayoutRepository()->search(new Criteria([$layoutId]), $context)->getEntities()->first()
        );
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
