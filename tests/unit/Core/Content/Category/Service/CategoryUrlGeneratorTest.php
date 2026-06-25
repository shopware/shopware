<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[CoversClass(CategoryUrlGenerator::class)]
class CategoryUrlGeneratorTest extends TestCase
{
    private const EXTERNAL_URL = 'https://shopware.com/';

    private CategoryUrlGenerator $urlGenerator;

    private Stub&EntityRouteResolver $entityRouteResolver;

    private SalesChannelEntity $salesChannel;

    protected function setUp(): void
    {
        $this->entityRouteResolver = static::createStub(EntityRouteResolver::class);
        $this->urlGenerator = new CategoryUrlGenerator($this->entityRouteResolver);
        $this->entityRouteResolver->method('generateSeoUrlPlaceholder')->willReturnArgument(0);
        $this->salesChannel = new SalesChannelEntity();
        $this->salesChannel->setNavigationCategoryId(Uuid::randomHex());
    }

    public function testPage(): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_PAGE);

        static::assertSame(CategoryDefinition::ENTITY_NAME, $this->urlGenerator->generate($category, $this->salesChannel));
    }

    public function testFolder(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_FOLDER);

        static::assertNull($this->urlGenerator->generate($category, $this->salesChannel));
    }

    #[DataProvider('dataProviderLinkTypes')]
    public function testLinkType(?string $type, string $route): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType($type);
        $category->addTranslated('linkType', $type);

        static::assertNull($this->urlGenerator->generate($category, $this->salesChannel));

        $category->setExternalLink(self::EXTERNAL_URL);
        $category->addTranslated('externalLink', $category->getExternalLink());
        $category->setInternalLink(Uuid::randomHex());
        $category->addTranslated('internalLink', $category->getInternalLink());

        static::assertSame($route, $this->urlGenerator->generate($category, $this->salesChannel));
    }

    /**
     * @return list<list<string|null>>
     */
    public static function dataProviderLinkTypes(): array
    {
        return [
            [CategoryDefinition::LINK_TYPE_PRODUCT, ProductDefinition::ENTITY_NAME],
            [CategoryDefinition::LINK_TYPE_CATEGORY, CategoryDefinition::ENTITY_NAME],
            [CategoryDefinition::LINK_TYPE_LANDING_PAGE, LandingPageDefinition::ENTITY_NAME],
            [CategoryDefinition::LINK_TYPE_EXTERNAL, self::EXTERNAL_URL],
            [null, self::EXTERNAL_URL],
        ];
    }
}
