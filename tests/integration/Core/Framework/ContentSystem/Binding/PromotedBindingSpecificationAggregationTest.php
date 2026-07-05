<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Covers the aggregation backstop end-to-end: two ACTIVE apps whose persisted rows both promote a
 * specification for the shared type `Sw:Media:Image`, which the core catalog's own inline `core:from-media-library`
 * binding also promotes. Three contenders reach {@see ContentSystemBindingSpecificationRegistry::all()}'s merge; the
 * promoted-uniqueness invariant leaves exactly one promoted specification for that type, the authored (YAML) core
 * binding wins over both persisted (DB) app rows, and a warning is logged per demoted row. Built against the real
 * filesystem and database loaders so the demotion is exercised over real persisted rows, not a double.
 *
 * @internal
 */
#[Package('framework')]
class PromotedBindingSpecificationAggregationTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const SHARED_TYPE = 'Sw:Media:Image';
    private const BINDING_NAME = 'promoted-image-binding';

    private string $firstAppName;

    private string $secondAppName;

    protected function setUp(): void
    {
        $context = Context::createDefaultContext();

        $this->firstAppName = 'AcmeOne' . Uuid::randomHex();
        $this->createBinding($this->createApp($this->firstAppName), self::BINDING_NAME, $this->firstAppName, $context);

        $this->secondAppName = 'AcmeTwo' . Uuid::randomHex();
        $this->createBinding($this->createApp($this->secondAppName), self::BINDING_NAME, $this->secondAppName, $context);
    }

    #[TestDox('two active apps promoting a type the core catalog already promotes aggregate to exactly one promoted specification (the authored core one), logging a warning per demoted row')]
    public function testAggregatesTwoPromotedRowsToOneWinnerAndLogsWarning(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * @var list<string>
             */
            public array $warnings = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if ($level === LogLevel::WARNING) {
                    $this->warnings[] = (string) $message;
                }
            }
        };

        $registry = new ContentSystemBindingSpecificationRegistry([$this->yamlLoader(), $this->databaseLoader()], $logger);

        // byType() returns a reindexed list, so the qualified ids are derived from the specifications themselves.
        $promotedIds = array_values(array_map(
            static fn (BindingSpecification $specification): string => $specification->source() . ':' . $specification->id(),
            array_filter(
                $registry->byType(self::SHARED_TYPE),
                static fn (BindingSpecification $specification): bool => $specification->isPromoted(),
            ),
        ));

        static::assertSame(['core:from-media-library'], $promotedIds, 'The backstop must keep exactly one promoted specification for the shared type, and the authored core binding must win over both persisted app rows.');
        static::assertCount(2, $logger->warnings, 'Both persisted app rows must be demoted, each logging its own warning.');
    }

    private function createApp(string $appName): string
    {
        $appId = Uuid::randomHex();

        $this->appRepository()->create([[
            'id' => $appId,
            'name' => $appName,
            'path' => $appName,
            'version' => '1.0.0',
            'label' => $appName,
            'active' => true,
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
        // Reuses core:from-media-library's shape so the real TypeConsistentBindingSpecificationValidator accepts it,
        // and adds promoted: true so both apps' rows contend for the per-type promoted slot.
        $dto = new BindingSpecificationDto(
            type: self::SHARED_TYPE,
            label: 'Promoted binding for ' . $appName,
            resolves: [
                'media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            ],
            inputs: [
                'mediaId' => [],
            ],
            promoted: true,
        );

        $this->bindingSpecificationRepository()->create([[
            'id' => Uuid::randomHex(),
            'appId' => $appId,
            'name' => $bindingName,
            'schema' => (new BindingSpecificationSerializer())->normalize($dto),
            'hash' => 'hash-' . $appName,
        ]], $context);
    }

    private function yamlLoader(): YamlBindingSpecificationLoader
    {
        $loader = $this->getContainer()->get(YamlBindingSpecificationLoader::class);
        static::assertInstanceOf(YamlBindingSpecificationLoader::class, $loader);

        return $loader;
    }

    private function databaseLoader(): DatabaseBindingSpecificationLoader
    {
        $loader = $this->getContainer()->get(DatabaseBindingSpecificationLoader::class);
        static::assertInstanceOf(DatabaseBindingSpecificationLoader::class, $loader);

        return $loader;
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
