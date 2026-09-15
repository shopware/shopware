<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteCollection;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\SeoUrlRouteLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SeoUrlRouteLifecycleHandler::class)]
class SeoUrlRouteLifecycleHandlerTest extends TestCase
{
    private const APP_ID = 'e5c0f9a0f0ab4f2a9d6d1f3a5d0a0001';
    private const IMPRINT_ROUTE = 'storefront.app.test.imprint';
    private const BLOG_ROUTE = 'storefront.app.test.blog-detail';

    /**
     * @var array<string, mixed>|false
     */
    private array|false $templateRow = false;

    /**
     * @var list<array{table: string, data: array<string, mixed>}>
     */
    private array $inserts = [];

    /**
     * @var list<array{table: string, data: array<string, mixed>, criteria: array<string, mixed>}>
     */
    private array $updates = [];

    /**
     * @var list<array{table: string, criteria: array<string, mixed>}>
     */
    private array $deletes = [];

    private Connection $connection;

    private ClockInterface $clock;

    protected function setUp(): void
    {
        $this->connection = $this->createConnection();
        $this->clock = new MockClock('2026-01-02 03:04:05');
    }

    public function testInstallPersistsTheDeclaredRoutes(): void
    {
        $repository = $this->createRepository(new AppSeoUrlRouteCollection());

        $this->createHandler($repository)->install($this->createPersistContext());

        static::assertSame([[
            [
                'name' => 'imprint',
                'hook' => 'imprint',
                'label' => ['en-GB' => 'Imprint', 'de-DE' => 'Impressum'],
                'defaultTemplate' => null,
                'entityName' => null,
                'paths' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
                'appId' => self::APP_ID,
                'routeName' => self::IMPRINT_ROUTE,
            ],
            [
                'name' => 'blog-detail',
                'hook' => 'blog-detail',
                'label' => ['en-GB' => 'Blog post'],
                'defaultTemplate' => 'blog/{{ ceBlog.translated.title }}',
                'entityName' => 'ce_blog',
                'paths' => null,
                'appId' => self::APP_ID,
                'routeName' => self::BLOG_ROUTE,
            ],
        ]], $repository->upserts);

        static::assertSame([], $repository->deletes);
    }

    public function testInstallSeedsTheDefaultTemplateOfEntityBoundRoutesOnly(): void
    {
        $this->createHandler($this->createRepository(new AppSeoUrlRouteCollection()))
            ->install($this->createPersistContext());

        static::assertCount(1, $this->inserts);
        static::assertSame('seo_url_template', $this->inserts[0]['table']);

        $data = $this->inserts[0]['data'];
        unset($data['id']);

        static::assertSame([
            'sales_channel_id' => null,
            'route_name' => self::BLOG_ROUTE,
            'entity_name' => 'ce_blog',
            'template' => 'blog/{{ ceBlog.translated.title }}',
            'is_valid' => 1,
            'is_headless' => 0,
            'created_at' => '2026-01-02 03:04:05.000',
        ], $data);
    }

    public function testUpdateOverwritesADefaultTemplateThatWasNotChangedByTheMerchant(): void
    {
        $this->templateRow = [
            'id' => 'aa11bb22cc33dd44ee55ff6600112233',
            'entityName' => 'ce_blog',
            'template' => 'blog/{{ ceBlog.id }}',
        ];

        $repository = $this->createRepository(new AppSeoUrlRouteCollection([
            $this->createRoute('blog-detail', self::BLOG_ROUTE, 'ce_blog', 'blog/{{ ceBlog.id }}'),
        ]));

        $this->createHandler($repository)->update($this->createPersistContext());

        static::assertSame([[
            'table' => 'seo_url_template',
            'data' => [
                'template' => 'blog/{{ ceBlog.translated.title }}',
                'updated_at' => '2026-01-02 03:04:05.000',
            ],
            'criteria' => ['id' => Uuid::fromHexToBytes('aa11bb22cc33dd44ee55ff6600112233')],
        ]], $this->updates);
    }

    public function testUpdateKeepsADefaultTemplateThatWasChangedByTheMerchant(): void
    {
        $this->templateRow = [
            'id' => 'aa11bb22cc33dd44ee55ff6600112233',
            'entityName' => 'ce_blog',
            'template' => 'my-blog/{{ ceBlog.id }}',
        ];

        $repository = $this->createRepository(new AppSeoUrlRouteCollection([
            $this->createRoute('blog-detail', self::BLOG_ROUTE, 'ce_blog', 'blog/{{ ceBlog.id }}'),
        ]));

        $this->createHandler($repository)->update($this->createPersistContext());

        static::assertSame([], $this->updates);
        static::assertSame([], $this->inserts);
    }

    public function testUpdateRemovesRoutesThatAreNoLongerDeclared(): void
    {
        $legacy = $this->createRoute('legacy', 'storefront.app.test.legacy');

        $repository = $this->createRepository(new AppSeoUrlRouteCollection([$legacy]));

        $this->createHandler($repository)->update($this->createPersistContext());

        static::assertSame([[['id' => $legacy->getId()]]], $repository->deletes);
        static::assertSame([
            ['table' => 'seo_url', 'criteria' => ['route_name' => 'storefront.app.test.legacy']],
            ['table' => 'seo_url_template', 'criteria' => ['route_name' => 'storefront.app.test.legacy']],
        ], $this->deletes);
    }

    public function testDeactivateMarksTheGeneratedSeoUrlsAsDeleted(): void
    {
        $repository = $this->createRepository(new AppSeoUrlRouteCollection([
            $this->createRoute('imprint', self::IMPRINT_ROUTE),
            $this->createRoute('blog-detail', self::BLOG_ROUTE, 'ce_blog', 'blog/{{ ceBlog.id }}'),
        ]));

        $this->createHandler($repository)->deactivate(new AppActivationContext($this->createApp(), Context::createDefaultContext()));

        static::assertSame([
            [
                'table' => 'seo_url',
                'data' => ['is_deleted' => 1, 'updated_at' => '2026-01-02 03:04:05.000'],
                'criteria' => ['route_name' => self::IMPRINT_ROUTE],
            ],
            [
                'table' => 'seo_url',
                'data' => ['is_deleted' => 1, 'updated_at' => '2026-01-02 03:04:05.000'],
                'criteria' => ['route_name' => self::BLOG_ROUTE],
            ],
        ], $this->updates);

        static::assertSame([], $this->deletes);
    }

    public function testUninstallRemovesTheGeneratedSeoUrlsAndTemplatesEvenWhenUserDataIsKept(): void
    {
        $repository = $this->createRepository(new AppSeoUrlRouteCollection([
            $this->createRoute('imprint', self::IMPRINT_ROUTE),
        ]));

        $this->createHandler($repository)->uninstall(
            new AppRemovalContext($this->createApp(), Context::createDefaultContext(), keepUserData: true)
        );

        static::assertSame([
            ['table' => 'seo_url', 'criteria' => ['route_name' => self::IMPRINT_ROUTE]],
            ['table' => 'seo_url_template', 'criteria' => ['route_name' => self::IMPRINT_ROUTE]],
        ], $this->deletes);
    }

    public function testDeleteRemovesTheGeneratedSeoUrlsAndTemplates(): void
    {
        $repository = $this->createRepository(new AppSeoUrlRouteCollection([
            $this->createRoute('imprint', self::IMPRINT_ROUTE),
        ]));

        $this->createHandler($repository)->delete(
            new AppRemovalContext($this->createApp(), Context::createDefaultContext())
        );

        static::assertSame([
            ['table' => 'seo_url', 'criteria' => ['route_name' => self::IMPRINT_ROUTE]],
            ['table' => 'seo_url_template', 'criteria' => ['route_name' => self::IMPRINT_ROUTE]],
        ], $this->deletes);
    }

    /**
     * @param StaticEntityRepository<AppSeoUrlRouteCollection> $repository
     */
    private function createHandler(StaticEntityRepository $repository): SeoUrlRouteLifecycleHandler
    {
        return new SeoUrlRouteLifecycleHandler($repository, $this->connection, $this->clock);
    }

    /**
     * @return StaticEntityRepository<AppSeoUrlRouteCollection>
     */
    private function createRepository(AppSeoUrlRouteCollection $existing): StaticEntityRepository
    {
        return new StaticEntityRepository([$existing]);
    }

    private function createPersistContext(): AppPersistContext
    {
        $path = __DIR__ . '/../../Manifest/_fixtures/test';

        return new AppPersistContext(
            Manifest::createFromXmlFile($path . '/manifest.xml'),
            $this->createApp(),
            Context::createDefaultContext(),
            new Filesystem($path),
            'en-GB'
        );
    }

    private function createApp(): AppEntity
    {
        $app = new AppEntity();
        $app->setId(self::APP_ID);
        $app->setName('test');

        return $app;
    }

    private function createRoute(
        string $name,
        string $routeName,
        ?string $entityName = null,
        ?string $defaultTemplate = null
    ): AppSeoUrlRouteEntity {
        $route = new AppSeoUrlRouteEntity();
        $route->setId(Uuid::randomHex());
        $route->setAppId(self::APP_ID);
        $route->setName($name);
        $route->setRouteName($routeName);
        $route->setHook($name);
        $route->setEntityName($entityName);
        $route->setDefaultTemplate($defaultTemplate);

        return $route;
    }

    private function createConnection(): Connection
    {
        $connection = static::createStub(Connection::class);

        $connection->method('fetchAssociative')->willReturnCallback(fn (): array|false => $this->templateRow);

        $connection->method('insert')->willReturnCallback(function (string $table, array $data): int {
            $this->inserts[] = ['table' => $table, 'data' => $data];

            return 1;
        });

        $connection->method('update')->willReturnCallback(function (string $table, array $data, array $criteria): int {
            $this->updates[] = ['table' => $table, 'data' => $data, 'criteria' => $criteria];

            return 1;
        });

        $connection->method('delete')->willReturnCallback(function (string $table, array $criteria): int {
            $this->deletes[] = ['table' => $table, 'criteria' => $criteria];

            return 1;
        });

        return $connection;
    }
}
