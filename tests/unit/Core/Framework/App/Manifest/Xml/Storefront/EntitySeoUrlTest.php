<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\EntitySeoUrl;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntitySeoUrl::class)]
class EntitySeoUrlTest extends TestCase
{
    public function testFromXmlReadsNameHookEntityAndDefaultTemplate(): void
    {
        $seoUrl = $this->parse(<<<'XML'
            <entity-seo-url name="blog-detail" entity="ce_blog" hook="blog-post">
                <default-template>blog/{{ ceBlog.translated.title }}</default-template>
            </entity-seo-url>
            XML);

        static::assertSame('blog-detail', $seoUrl->getName());
        static::assertSame('blog-post', $seoUrl->getHook());
        static::assertSame('ce_blog', $seoUrl->getEntity());
        static::assertSame('blog/{{ ceBlog.translated.title }}', $seoUrl->getDefaultTemplate());
    }

    public function testFromXmlDefaultsTheHookToTheName(): void
    {
        $seoUrl = $this->parse(<<<'XML'
            <entity-seo-url name="blog-detail" entity="ce_blog">
                <default-template>blog/{{ ceBlog.translated.title }}</default-template>
            </entity-seo-url>
            XML);

        static::assertSame('blog-detail', $seoUrl->getHook());
    }

    public function testFromXmlTrimsTheDefaultTemplate(): void
    {
        $seoUrl = $this->parse(<<<'XML'
            <entity-seo-url name="blog-detail" entity="ce_blog">
                <default-template>
                    blog/{{ ceBlog.translated.title }}
                </default-template>
            </entity-seo-url>
            XML);

        static::assertSame('blog/{{ ceBlog.translated.title }}', $seoUrl->getDefaultTemplate());
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('missingRequiredFieldProvider')]
    public function testFromArrayRequiresTheField(array $data, string $missingField): void
    {
        $this->expectExceptionObject(AppException::invalidArgument($missingField . ' must not be empty'));

        EntitySeoUrl::fromArray($data);
    }

    /**
     * @return iterable<string, array{data: array<string, string>, missingField: string}>
     */
    public static function missingRequiredFieldProvider(): iterable
    {
        yield 'a declaration without name is rejected' => [
            'data' => ['entity' => 'ce_blog', 'defaultTemplate' => 'blog/{{ ceBlog.translated.title }}'],
            'missingField' => 'name',
        ];

        yield 'a declaration without entity is rejected' => [
            'data' => ['name' => 'blog-detail', 'defaultTemplate' => 'blog/{{ ceBlog.translated.title }}'],
            'missingField' => 'entity',
        ];

        yield 'a declaration without default template is rejected' => [
            'data' => ['name' => 'blog-detail', 'entity' => 'ce_blog'],
            'missingField' => 'defaultTemplate',
        ];
    }

    private function parse(string $xml): EntitySeoUrl
    {
        $element = XmlUtils::parse($xml)->documentElement;
        static::assertInstanceOf(\DOMElement::class, $element);

        return EntitySeoUrl::fromXml($element);
    }
}
