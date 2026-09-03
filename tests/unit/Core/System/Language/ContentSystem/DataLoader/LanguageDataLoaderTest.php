<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Language\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageDataLoader;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageException;
use Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Shopware\Core\System\Language\SalesChannel\LanguageRouteResponse;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LanguageDataLoader::class)]
class LanguageDataLoaderTest extends TestCase
{
    private LanguageDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->dataLoader = new LanguageDataLoader(static::createStub(AbstractLanguageRoute::class));
    }

    #[TestDox('returns language source type identifier')]
    public function testGetRequirementTypeReturnsLanguageString(): void
    {
        static::assertSame('language', LanguageDataLoader::getRequirementType());
    }

    #[TestDox('declares LanguageCollection as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->dataLoader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(LanguageCollection::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('loads languages and returns cachedExternally result with correct request, context and empty criteria')]
    public function testLoadWithDefaultConfig(): void
    {
        $languages = new LanguageCollection();
        $response = $this->createLanguageRouteResponse($languages);

        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $languageRoute = static::createStub(AbstractLanguageRoute::class);
        $languageRoute
            ->method('load')
            ->willReturn($response);

        $dataLoader = new LanguageDataLoader($languageRoute);
        $result = $dataLoader->load(
            new LoaderInputs(['associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertTrue($result->hasData());
        static::assertSame($languages, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('adds the associations input to criteria')]
    public function testLoadAddsAssociationsFromConfigToCriteria(): void
    {
        $languages = new LanguageCollection();
        $response = $this->createLanguageRouteResponse($languages);

        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $languageRoute = $this->createMock(AbstractLanguageRoute::class);
        $languageRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::anything(),
                static::anything(),
                static::callback(function (Criteria $criteria): bool {
                    static::assertContains('locale', array_keys($criteria->getAssociations()));
                    static::assertContains('translationCode', array_keys($criteria->getAssociations()));

                    return true;
                })
            )
            ->willReturn($response);

        $dataLoader = new LanguageDataLoader($languageRoute);
        $dataLoader->load(
            new LoaderInputs(['associations' => ['locale', 'translationCode']]),
            self::requirement(),
            $context,
            $request,
        );
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the language route throws the Shopware exception $_dataName')]
    public function testLoadReturnsNotFoundWhenLanguageRouteThrows(\Throwable $exception): void
    {
        $context = Generator::generateSalesChannelContext();

        $languageRoute = static::createStub(AbstractLanguageRoute::class);
        $languageRoute
            ->method('load')
            ->willThrowException($exception);

        $dataLoader = new LanguageDataLoader($languageRoute);
        $result = $dataLoader->load(
            new LoaderInputs(['associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertFalse($result->hasData());
        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the language route propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        $context = Generator::generateSalesChannelContext();

        $typeError = new \TypeError('Argument #3 ($criteria) must be of type Criteria, null given');

        $languageRoute = static::createStub(AbstractLanguageRoute::class);
        $languageRoute
            ->method('load')
            ->willThrowException($typeError);

        $dataLoader = new LanguageDataLoader($languageRoute);

        // expectExceptionObject() compares class, message and code, not object identity; this test
        // asserts the same instance propagated out of load() unmodified, which that helper can't express.
        try {
            $dataLoader->load(
                new LoaderInputs(['associations' => []]),
                self::requirement(),
                $context,
                new Request(),
            );

            static::fail('Expected the TypeError to propagate out of load() instead of degrading to notFound');
        } catch (\TypeError $caught) {
            static::assertSame($typeError, $caught);
        }
    }

    /**
     * Neither row is a reachability claim: LanguageRoute::load() reaches no domain exception today. Both
     * rows state the loader's contract instead, that any `ShopwareHttpException` degrades.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        // LanguageException extends HttpException, which extends ShopwareHttpException.
        yield 'a language domain exception, reached through HttpException' => [
            LanguageException::invalidFieldValueType('localeId', 'string', 'int'),
        ];

        // DecorationPatternException extends ShopwareHttpException directly instead of through
        // HttpException, so a clause narrowed to one branch of that line would let it escape.
        yield 'a class outside the chain that extends ShopwareHttpException directly' => [
            new DecorationPatternException(AbstractLanguageRoute::class),
        ];
    }

    #[TestDox('propagates a RuntimeException the language route throws')]
    public function testLoadPropagatesAnExceptionTheLanguageRouteThrows(): void
    {
        $exception = new \RuntimeException('language route failed');
        $languageRoute = static::createStub(AbstractLanguageRoute::class);
        $languageRoute->method('load')->willThrowException($exception);

        $dataLoader = new LanguageDataLoader($languageRoute);

        $this->expectExceptionObject($exception);

        $dataLoader->load(
            new LoaderInputs(['associations' => []]),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('languages', 'language', new LanguageLoaderConfig());
    }

    private function createLanguageRouteResponse(LanguageCollection $languages): LanguageRouteResponse
    {
        $response = static::createStub(LanguageRouteResponse::class);
        $response->method('getLanguages')->willReturn($languages);

        return $response;
    }
}
