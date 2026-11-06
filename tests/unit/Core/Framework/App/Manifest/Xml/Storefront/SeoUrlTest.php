<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SeoUrl::class)]
class SeoUrlTest extends TestCase
{
    public function testFromXmlReadsNameHookAndTranslatedPaths(): void
    {
        $seoUrl = $this->parse(<<<'XML'
            <seo-url name="imprint" hook="legal-notice">
                <path>imprint</path>
                <path lang="de-DE">impressum</path>
            </seo-url>
            XML);

        static::assertSame('imprint', $seoUrl->getName());
        static::assertSame('legal-notice', $seoUrl->getHook());
        static::assertSame(['en-GB' => 'imprint', 'de-DE' => 'impressum'], $seoUrl->getPath());
    }

    public function testFromXmlDefaultsTheHookToTheName(): void
    {
        $seoUrl = $this->parse('<seo-url name="imprint"><path>imprint</path></seo-url>');

        static::assertSame('imprint', $seoUrl->getHook());
    }

    public function testFromXmlTrimsThePaths(): void
    {
        $seoUrl = $this->parse(<<<'XML'
            <seo-url name="imprint">
                <path>  imprint  </path>
                <path lang="de-DE">
                    impressum
                </path>
            </seo-url>
            XML);

        static::assertSame(['en-GB' => 'imprint', 'de-DE' => 'impressum'], $seoUrl->getPath());
    }

    public function testToArrayReturnsNameHookAndPath(): void
    {
        $seoUrl = SeoUrl::fromArray([
            'name' => 'imprint',
            'hook' => 'legal-notice',
            'path' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
        ]);

        static::assertSame([
            'name' => 'imprint',
            'hook' => 'legal-notice',
            'path' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
        ], $seoUrl->toArray('en-GB'));
    }

    /**
     * @param array<string, string> $path
     * @param array<string, string> $expectedPath
     */
    #[DataProvider('defaultLocaleProvider')]
    public function testToArrayBackfillsThePathForTheDefaultLocale(array $path, string $defaultLocale, array $expectedPath): void
    {
        $seoUrl = SeoUrl::fromArray(['name' => 'imprint', 'path' => $path]);

        static::assertSame(
            ['name' => 'imprint', 'hook' => 'imprint', 'path' => $expectedPath],
            $seoUrl->toArray($defaultLocale)
        );
    }

    /**
     * @return iterable<string, array{path: array<string, string>, defaultLocale: string, expectedPath: array<string, string>}>
     */
    public static function defaultLocaleProvider(): iterable
    {
        yield 'a path declared for the default locale is kept untouched' => [
            'path' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
            'defaultLocale' => 'de-DE',
            'expectedPath' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
        ];

        yield 'a missing default locale prefers the en-GB path over the first one' => [
            'path' => ['de-DE' => 'impressum', 'en-GB' => 'imprint'],
            'defaultLocale' => 'fr-FR',
            'expectedPath' => ['de-DE' => 'impressum', 'en-GB' => 'imprint', 'fr-FR' => 'imprint'],
        ];

        yield 'a missing default locale without en-GB path falls back to the first path' => [
            'path' => ['de-DE' => 'impressum', 'nl-NL' => 'colofon'],
            'defaultLocale' => 'fr-FR',
            'expectedPath' => ['de-DE' => 'impressum', 'nl-NL' => 'colofon', 'fr-FR' => 'impressum'],
        ];
    }

    public function testFromArrayWithOnlyANameDefaultsTheHookAndHasNoPaths(): void
    {
        $seoUrl = SeoUrl::fromArray(['name' => 'imprint']);

        static::assertSame('imprint', $seoUrl->getName());
        static::assertSame('imprint', $seoUrl->getHook());
        static::assertSame([], $seoUrl->getPath());
    }

    public function testFromArrayRequiresAName(): void
    {
        $this->expectExceptionObject(AppException::invalidArgument('name must not be empty'));

        SeoUrl::fromArray(['hook' => 'imprint', 'path' => ['en-GB' => 'imprint']]);
    }

    private function parse(string $xml): SeoUrl
    {
        $element = XmlUtils::parse($xml)->documentElement;
        static::assertInstanceOf(\DOMElement::class, $element);

        return SeoUrl::fromXml($element);
    }
}
