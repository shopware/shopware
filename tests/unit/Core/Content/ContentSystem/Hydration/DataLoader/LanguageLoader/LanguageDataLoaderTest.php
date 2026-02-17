<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\LanguageLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\LanguageLoader\LanguageDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\LanguageLoader\LanguageLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Shopware\Core\System\Language\SalesChannel\LanguageRouteResponse;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
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
            ->with(
                $request,
                $context,
                static::callback(static function (Criteria $criteria): bool {
                    return $criteria->getAssociations() === [];
                })
            )
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
            ->method('load')
            ->with(
                $request,
                $context,
                static::callback(static function (Criteria $criteria): bool {
                    $associations = $criteria->getAssociations();

                    return isset($associations['locale'], $associations['translationCode']);
                })
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $request);

        static::assertTrue($result->hasData());
        static::assertSame($languages, $result->data);
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
            ->with(
                $request,
                $context,
                static::callback(static function (Criteria $criteria): bool {
                    return $criteria->getAssociations() === [];
                })
            )
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $request);

        static::assertTrue($result->hasData());
        static::assertSame($languages, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->dataLoader->getDecorated();
    }

    private function createLanguageRouteResponse(LanguageCollection $languages): LanguageRouteResponse
    {
        $response = static::createStub(LanguageRouteResponse::class);
        $response->method('getLanguages')->willReturn($languages);

        return $response;
    }
}
