<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\Api\SeoActionController;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Content\Seo\Validation\SeoUrlDataValidationFactoryInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoActionController::class)]
class SeoActionControllerTest extends TestCase
{
    #[TestDox('Route $routeName is guarded by the seo_url_template:update ACL privilege')]
    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresSeoUrlTemplateUpdatePrivilege(string $routeName): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(SeoActionController::class);

        $route = $routes->get($routeName);

        static::assertNotNull(
            $route,
            \sprintf('Route "%s" is not defined on %s', $routeName, SeoActionController::class)
        );
        static::assertSame(['seo_url_template:update'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'validate' => ['api.seo-url-template.validate'];
        yield 'preview' => ['api.seo-url-template.preview'];
    }

    /**
     * @param list<string> $expectedPrivileges
     */
    #[DataProvider('aclProtectedSeoUrlRouteProvider')]
    public function testRouteRequiresSeoUrlPrivilege(string $routeName, array $expectedPrivileges): void
    {
        $route = (new AttributeRouteControllerLoader())->load(SeoActionController::class)->get($routeName);

        static::assertNotNull(
            $route,
            \sprintf('Route "%s" is not defined on %s', $routeName, SeoActionController::class)
        );
        static::assertSame($expectedPrivileges, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return \Generator<string, array{0: string, 1: list<string>}>
     */
    public static function aclProtectedSeoUrlRouteProvider(): \Generator
    {
        yield 'updating a canonical url requires seo url write access' => ['api.seo-url.canonical', ['seo_url:update']];
        yield 'creating custom urls requires seo url create access' => ['api.seo-url.create', ['seo_url:create']];
        yield 'reading the template context requires template read access' => ['api.seo-url-template.context', ['seo_url_template:read']];
        yield 'reading the default template requires template read access' => ['api.seo-url-template.default', ['seo_url_template:read']];
    }

    /**
     * @param array<string, mixed> $clearedFields
     */
    #[DataProvider('clearedSeoPathInfoProvider')]
    public function testClearedSeoPathInfoResetsCanonicalToTemplate(array $clearedFields): void
    {
        $seoUrlRepository = new StaticEntityRepository([['protected-canonical-id']], new SeoUrlDefinition());
        $persister = $this->createMock(SeoUrlPersister::class);
        $persister->expects($this->never())->method('forceUpdateSeoUrls');
        $updater = $this->createMock(SeoUrlUpdater::class);
        $updater->expects($this->once())->method('update')->with(ProductPageSeoUrlRoute::ROUTE_NAME, ['product-id']);
        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())->method('validate')->with(
            static::anything(),
            static::callback(static fn (DataValidationDefinition $definition): bool => $definition->getProperty('seoPathInfo') === []),
        );

        $response = $this->createController($seoUrlRepository, $persister, $updater, $validator)
            ->updateCanonicalUrl(new RequestDataBag([...$this->seoUrlPayload(), ...$clearedFields]), Context::createDefaultContext());

        static::assertSame(204, $response->getStatusCode());
        static::assertSame([[['id' => 'protected-canonical-id', 'isModified' => false]]], $seoUrlRepository->updates);
    }

    /**
     * @return \Generator<string, array{array<string, mixed>}>
     */
    public static function clearedSeoPathInfoProvider(): \Generator
    {
        yield 'empty' => [['seoPathInfo' => '']];
        yield 'missing' => [[]];
        yield 'blank' => [['seoPathInfo' => '  ']];
    }

    public function testClearedSeoPathInfoRegeneratesWithoutWriteProtectedCanonical(): void
    {
        $seoUrlRepository = new StaticEntityRepository([[]], new SeoUrlDefinition());
        $updater = $this->createMock(SeoUrlUpdater::class);
        $updater->expects($this->once())->method('update')->with(ProductPageSeoUrlRoute::ROUTE_NAME, ['product-id']);

        $this->createController($seoUrlRepository, static::createStub(SeoUrlPersister::class), $updater, static::createStub(DataValidator::class))
            ->updateCanonicalUrl(new RequestDataBag([...$this->seoUrlPayload(), 'seoPathInfo' => '']), Context::createDefaultContext());

        static::assertSame([], $seoUrlRepository->updates);
    }

    public function testSeoPathInfoIsPersistedAsModifiedUrl(): void
    {
        $seoUrlRepository = new StaticEntityRepository([], new SeoUrlDefinition());
        $persister = $this->createMock(SeoUrlPersister::class);
        $persister->expects($this->once())->method('forceUpdateSeoUrls')->with(
            static::anything(),
            ProductPageSeoUrlRoute::ROUTE_NAME,
            ['product-id'],
            [[...$this->seoUrlPayload(), 'seoPathInfo' => 'custom-path', 'isModified' => true]],
        );
        $updater = $this->createMock(SeoUrlUpdater::class);
        $updater->expects($this->never())->method('update');
        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())->method('validate')->with(
            static::anything(),
            static::callback(static fn (DataValidationDefinition $definition): bool => $definition->getProperty('seoPathInfo') !== []),
        );

        $this->createController($seoUrlRepository, $persister, $updater, $validator)
            ->updateCanonicalUrl(new RequestDataBag([...$this->seoUrlPayload(), 'seoPathInfo' => 'custom-path']), Context::createDefaultContext());

        static::assertSame([], $seoUrlRepository->updates);
    }

    /**
     * @return array<string, string>
     */
    private function seoUrlPayload(): array
    {
        return [
            'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'foreignKey' => 'product-id',
            'pathInfo' => '/detail/product-id',
            'salesChannelId' => 'sales-channel-id',
        ];
    }

    /**
     * @param StaticEntityRepository<SeoUrlCollection> $seoUrlRepository
     */
    private function createController(
        StaticEntityRepository $seoUrlRepository,
        SeoUrlPersister $persister,
        SeoUrlUpdater $updater,
        DataValidator $validator,
    ): SeoActionController {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('getRepository')->willReturn($seoUrlRepository);

        $validation = (new DataValidationDefinition())->add('seoPathInfo', new NotBlank());
        $validationFactory = static::createStub(SeoUrlDataValidationFactoryInterface::class);
        $validationFactory->method('buildValidation')->willReturn($validation);

        $routeRegistry = new SeoUrlRouteRegistry([new ProductPageSeoUrlRoute(new ProductDefinition())]);

        return new SeoActionController(
            $this->createMock(SeoUrlGenerator::class),
            $persister,
            $definitionRegistry,
            $routeRegistry,
            $validationFactory,
            $validator,
            new StaticEntityRepository([new SalesChannelCollection([$salesChannel])]),
            $this->createMock(RequestCriteriaBuilder::class),
            $definitionRegistry,
            new EntityRouteResolver($routeRegistry, $this->createMock(SeoUrlPlaceholderHandlerInterface::class), $this->createMock(RouterInterface::class)),
            $updater,
        );
    }
}
