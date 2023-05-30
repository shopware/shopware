<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
class CategoryUrlGeneratorTest extends TestCase
{
    private const EXTERNAL_URL = 'https://shopware.com/';

    private CategoryUrlGenerator $urlGenerator;

    private MockObject&SeoUrlPlaceholderHandlerInterface $replacer;

    private SalesChannelEntity $salesChannel;

    protected function setUp(): void
    {
        $this->replacer = $this->getMockBuilder(SeoUrlPlaceholderHandlerInterface::class)->getMock();
        $this->urlGenerator = new CategoryUrlGenerator($this->replacer);
        $this->replacer->method('generate')->willReturnArgument(0);
        $this->salesChannel = new SalesChannelEntity();
        $this->salesChannel->setNavigationCategoryId(Uuid::randomHex());
    }

    public function testPage(): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_PAGE);

        static::assertSame('frontend.navigation.page', $this->urlGenerator->generate($category, $this->salesChannel));
    }

    public function testFolder(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_FOLDER);

        static::assertNull($this->urlGenerator->generate($category, $this->salesChannel));
    }

    public function testLinkCategoryHome(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType(CategoryDefinition::LINK_TYPE_CATEGORY);
        $category->addTranslated('linkType', CategoryDefinition::LINK_TYPE_CATEGORY);
        $category->setInternalLink(Uuid::randomHex());
        $category->addTranslated('internalLink', $category->getInternalLink());
        $this->salesChannel->setNavigationCategoryId($category->getInternalLink());

        static::assertSame('frontend.home.page', $this->urlGenerator->generate($category, $this->salesChannel));
    }

    /**
     * @dataProvider dataProviderLinkTypes
     */
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

    public static function dataProviderLinkTypes(): array
    {
        return [
            [CategoryDefinition::LINK_TYPE_PRODUCT, 'frontend.detail.page'],
            [CategoryDefinition::LINK_TYPE_CATEGORY, 'frontend.navigation.page'],
            [CategoryDefinition::LINK_TYPE_LANDING_PAGE, 'frontend.landing.page'],
            [CategoryDefinition::LINK_TYPE_EXTERNAL, self::EXTERNAL_URL],
            [null, self::EXTERNAL_URL],
        ];
    }
}
