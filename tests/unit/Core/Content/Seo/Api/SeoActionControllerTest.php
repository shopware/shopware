<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\Api\SeoActionController;
use Shopware\Core\Content\Seo\SeoException;
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
use Symfony\Component\Validator\Constraints\Blank;
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
    public function testClearedSeoPathInfoResetsTheUrlToItsTemplate(array $clearedFields): void
    {
        $persister = $this->createMock(SeoUrlPersister::class);
        $persister->expects($this->never())->method('forceUpdateSeoUrls');
        $updater = $this->createMock(SeoUrlUpdater::class);
        $updater->expects($this->once())->method('resetToTemplate')
            ->with(ProductPageSeoUrlRoute::ROUTE_NAME, 'product-id', 'sales-channel-id', Defaults::LANGUAGE_SYSTEM, false)
            ->willReturn(true);
        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())->method('validate')->with(
            static::callback(static fn (array $data): bool => $data['seoPathInfo'] === null),
            static::callback(static fn (DataValidationDefinition $definition): bool => self::hasOnly($definition, Blank::class)),
        );

        $response = $this->createController($persister, $updater, $validator)
            ->updateCanonicalUrl(new RequestDataBag([...$this->seoUrlPayload(), ...$clearedFields]), Context::createDefaultContext());

        static::assertSame(204, $response->getStatusCode());
    }

    /**
     * @return \Generator<string, array{array<string, mixed>}>
     */
    public static function clearedSeoPathInfoProvider(): \Generator
    {
        yield 'an empty seo path resets the url to its template' => [['seoPathInfo' => '', 'isModified' => false]];
        yield 'a missing seo path resets the url to its template' => [[]];
        yield 'a whitespace-only seo path resets the url to its template' => [['seoPathInfo' => '  ']];
    }

    public function testResetIsRejectedWhenTheTemplateGeneratesNoUrl(): void
    {
        $updater = $this->createMock(SeoUrlUpdater::class);
        $updater->expects($this->once())->method('resetToTemplate')->willReturn(false);

        $this->expectExceptionObject(SeoException::seoUrlResetNotPossible('product-id', 'sales-channel-id'));

        $this->createController(static::createStub(SeoUrlPersister::class), $updater, static::createStub(DataValidator::class))
            ->updateCanonicalUrl(new RequestDataBag([...$this->seoUrlPayload(), 'seoPathInfo' => '']), Context::createDefaultContext());
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('notAResetProvider')]
    public function testSeoPathInfoIsValidatedWhenTheRequestIsNotAReset(array $fields): void
    {
        $updater = $this->createMock(SeoUrlUpdater::class);
        $updater->expects($this->never())->method('resetToTemplate');
        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())->method('validate')
            ->with(
                static::anything(),
                static::callback(static fn (DataValidationDefinition $definition): bool => self::hasOnly($definition, NotBlank::class)),
            )
            ->willThrowException(new \RuntimeException('seoPathInfo is invalid'));

        $this->expectExceptionObject(new \RuntimeException('seoPathInfo is invalid'));

        $this->createController(static::createStub(SeoUrlPersister::class), $updater, $validator)
            ->updateCanonicalUrl(new RequestDataBag([...$this->seoUrlPayload(), ...$fields]), Context::createDefaultContext());
    }

    /**
     * @return \Generator<string, array{array<string, mixed>}>
     */
    public static function notAResetProvider(): \Generator
    {
        yield 'an empty seo path that asks for write protection is validated' => [['seoPathInfo' => '', 'isModified' => true]];
        yield 'a boolean seo path is validated' => [['seoPathInfo' => false]];
        yield 'a list as seo path is validated' => [['seoPathInfo' => ['custom-path']]];
    }

    public function testSeoPathInfoIsPersistedAsModifiedUrl(): void
    {
        $persister = $this->createMock(SeoUrlPersister::class);
        $persister->expects($this->once())->method('forceUpdateSeoUrls')->with(
            static::anything(),
            ProductPageSeoUrlRoute::ROUTE_NAME,
            ['product-id'],
            [[...$this->seoUrlPayload(), 'seoPathInfo' => 'custom-path', 'isModified' => true]],
        );
        $updater = $this->createMock(SeoUrlUpdater::class);
        $updater->expects($this->never())->method('resetToTemplate');

        $response = $this->createController($persister, $updater, static::createStub(DataValidator::class))
            ->updateCanonicalUrl(new RequestDataBag([...$this->seoUrlPayload(), 'seoPathInfo' => 'custom-path']), Context::createDefaultContext());

        static::assertSame(204, $response->getStatusCode());
    }

    /**
     * @param class-string $constraintClass
     */
    private static function hasOnly(DataValidationDefinition $definition, string $constraintClass): bool
    {
        $constraints = $definition->getProperty('seoPathInfo');

        return \count($constraints) === 1 && $constraints[0] instanceof $constraintClass;
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

    private function createController(SeoUrlPersister $persister, SeoUrlUpdater $updater, DataValidator $validator): SeoActionController
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $validation = (new DataValidationDefinition())->add('seoPathInfo', new NotBlank());
        $validationFactory = static::createStub(SeoUrlDataValidationFactoryInterface::class);
        $validationFactory->method('buildValidation')->willReturn($validation);

        $routeRegistry = new SeoUrlRouteRegistry([new ProductPageSeoUrlRoute(new ProductDefinition())]);
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        return new SeoActionController(
            static::createStub(SeoUrlGenerator::class),
            $persister,
            $definitionRegistry,
            $routeRegistry,
            $validationFactory,
            $validator,
            new StaticEntityRepository([new SalesChannelCollection([$salesChannel])]),
            static::createStub(RequestCriteriaBuilder::class),
            $definitionRegistry,
            new EntityRouteResolver($routeRegistry, static::createStub(SeoUrlPlaceholderHandlerInterface::class), static::createStub(RouterInterface::class)),
            $updater,
        );
    }
}
