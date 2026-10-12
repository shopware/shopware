<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\EntitySeoUrl;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\Storefront;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Storefront::class)]
class StorefrontTest extends TestCase
{
    public function testFromXmlReadsTemplateLoadPriorityAndBothSeoUrlListsInDeclarationOrder(): void
    {
        $storefront = $this->parse(<<<'XML'
            <storefront>
                <template-load-priority>100</template-load-priority>
                <seo-url name="imprint">
                    <path>imprint</path>
                </seo-url>
                <entity-seo-url name="blog-detail" entity="ce_blog">
                    <default-template>blog/{{ ceBlog.translated.title }}</default-template>
                </entity-seo-url>
                <seo-url name="blog">
                    <path>blog</path>
                </seo-url>
                <entity-seo-url name="author-detail" entity="ce_author">
                    <default-template>author/{{ ceAuthor.name }}</default-template>
                </entity-seo-url>
            </storefront>
            XML);

        static::assertSame(100, $storefront->getTemplateLoadPriority());
        static::assertSame(
            ['imprint', 'blog'],
            array_map(static fn (SeoUrl $seoUrl): string => $seoUrl->getName(), $storefront->getSeoUrls())
        );
        static::assertSame(
            ['blog-detail', 'author-detail'],
            array_map(static fn (EntitySeoUrl $seoUrl): string => $seoUrl->getName(), $storefront->getEntitySeoUrls())
        );
    }

    public function testFromXmlWithoutChildrenFallsBackToDefaults(): void
    {
        $storefront = $this->parse('<storefront/>');

        static::assertSame(0, $storefront->getTemplateLoadPriority());
        static::assertSame([], $storefront->getSeoUrls());
        static::assertSame([], $storefront->getEntitySeoUrls());
    }

    private function parse(string $xml): Storefront
    {
        $element = XmlUtils::parse($xml)->documentElement;
        static::assertInstanceOf(\DOMElement::class, $element);

        return Storefront::fromXml($element);
    }
}
