<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Language\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageDataLoader;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\System\Language\LanguageCollection;
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
    private AbstractLanguageRoute&Stub $languageRoute;

    private LanguageDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->languageRoute = static::createStub(AbstractLanguageRoute::class);
        $this->dataLoader = new LanguageDataLoader($this->languageRoute);
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

        $this->languageRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load(
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
