<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Language\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
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
#[CoversClass(LanguageDataLoader::class)]
class LanguageDataLoaderTest extends TestCase
{
    private AbstractLanguageRoute&MockObject $languageRoute;

    private LanguageDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->languageRoute = $this->createMock(AbstractLanguageRoute::class);
        $this->dataLoader = new LanguageDataLoader($this->languageRoute);
    }

    #[TestDox('returns language source type identifier')]
    public function testGetRequirementTypeReturnsLanguageString(): void
    {
        static::assertSame('language', LanguageDataLoader::getRequirementType());
    }

    #[TestDox('resolves provided data type from annotation')]
    public function testGetProvidedDataResolvesExpectedType(): void
    {
        $descriptor = LanguageDataLoader::getProvidedData();

        static::assertSame(LanguageCollection::class, $descriptor->className);
        static::assertSame([], $descriptor->genericParameters);
    }

    #[TestDox('loads languages and returns cachedExternally result with correct request, context and empty criteria')]
    public function testLoadWithDefaultConfig(): void
    {
        $languages = new LanguageCollection();
        $response = $this->createLanguageRouteResponse($languages);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new LanguageLoaderConfig();
        $requirement = new DataRequirement('languages', 'language', $config);
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $this->languageRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $request);

        static::assertTrue($result->hasData());
        static::assertSame($languages, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('adds associations from LanguageLoaderConfig to criteria')]
    public function testLoadAddsAssociationsFromConfigToCriteria(): void
    {
        $languages = new LanguageCollection();
        $response = $this->createLanguageRouteResponse($languages);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new LanguageLoaderConfig(associations: ['locale', 'translationCode']);
        $requirement = new DataRequirement('languages', 'language', $config);
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $this->languageRoute
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

        $this->dataLoader->load($element, $requirement, $context, $request);
    }

    #[TestDox('loads languages without associations when config is not a LanguageLoaderConfig instance')]
    public function testLoadWithWrongConfigTypeSkipsAssociations(): void
    {
        $languages = new LanguageCollection();
        $response = $this->createLanguageRouteResponse($languages);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('languages', 'language', $wrongConfig);
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $this->languageRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $request);

        static::assertTrue($result->hasData());
        static::assertSame($languages, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    private function createLanguageRouteResponse(LanguageCollection $languages): LanguageRouteResponse
    {
        $response = static::createStub(LanguageRouteResponse::class);
        $response->method('getLanguages')->willReturn($languages);

        return $response;
    }
}
