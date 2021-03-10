<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryUrlGeneratorTest extends TestCase
{
    private const EXTERNAL_URL = 'https://shopware.com/';

    /**
     * @var CategoryUrlGenerator
     */
    private $urlGenerator;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $replacer;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_13504', $this);

        $this->replacer = $this->getMockBuilder(SeoUrlPlaceholderHandlerInterface::class)->getMock();
        $this->urlGenerator = new CategoryUrlGenerator($this->replacer);
        $this->replacer->method('generate')->willReturnArgument(0);
    }

    public function testPage(): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_PAGE);

        static::assertSame('frontend.navigation.page', $this->urlGenerator->generate($category));
    }

    public function testFolder(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_FOLDER);

        static::assertNull($this->urlGenerator->generate($category));
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

        static::assertNull($this->urlGenerator->generate($category));

        $category->setExternalLink(self::EXTERNAL_URL);
        $category->addTranslated('externalLink', $category->getExternalLink());
        $category->setInternalLink(Uuid::randomHex());
        $category->addTranslated('internalLink', $category->getInternalLink());

        static::assertSame($route, $this->urlGenerator->generate($category));
    }

    public function dataProviderLinkTypes(): array
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
