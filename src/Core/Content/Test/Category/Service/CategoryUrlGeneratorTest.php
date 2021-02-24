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

    public function testLinkCategory(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType(CategoryDefinition::LINK_TYPE_CATEGORY);
        $category->addTranslated('linkType', CategoryDefinition::LINK_TYPE_CATEGORY);
        $category->setInternalLink(Uuid::randomHex());
        $category->addTranslated('internalLink', $category->getInternalLink());

        static::assertSame('frontend.navigation.page', $this->urlGenerator->generate($category));
    }

    public function testLinkProduct(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType(CategoryDefinition::LINK_TYPE_PRODUCT);
        $category->addTranslated('linkType', CategoryDefinition::LINK_TYPE_PRODUCT);
        $category->setInternalLink(Uuid::randomHex());
        $category->addTranslated('internalLink', $category->getInternalLink());

        static::assertSame('frontend.detail.page', $this->urlGenerator->generate($category));
    }

    public function testLinkLandingPage(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType(CategoryDefinition::LINK_TYPE_CATEGORY);
        $category->addTranslated('linkType', CategoryDefinition::LINK_TYPE_LANDING_PAGE);
        $category->setInternalLink(Uuid::randomHex());
        $category->addTranslated('internalLink', $category->getInternalLink());

        static::assertSame('frontend.landing.page', $this->urlGenerator->generate($category));
    }

    public function testLinkExternalTypeSet(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType(CategoryDefinition::LINK_TYPE_EXTERNAL);
        $category->addTranslated('linkType', CategoryDefinition::LINK_TYPE_EXTERNAL);
        $category->setExternalLink(self::EXTERNAL_URL);
        $category->addTranslated('externalLink', $category->getExternalLink());

        static::assertSame(self::EXTERNAL_URL, $this->urlGenerator->generate($category));
    }

    public function testLinkExternalTypeNotSet(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setExternalLink(self::EXTERNAL_URL);
        $category->addTranslated('externalLink', $category->getExternalLink());

        static::assertSame(self::EXTERNAL_URL, $this->urlGenerator->generate($category));
    }
}
