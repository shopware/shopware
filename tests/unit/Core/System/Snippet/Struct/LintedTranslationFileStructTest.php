<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\LintedTranslationFileStruct;
use Shopware\Core\System\Snippet\Struct\TranslationFile;
use Shopware\Core\System\Snippet\Struct\TranslationFileCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LintedTranslationFileStruct::class)]
class LintedTranslationFileStructTest extends TestCase
{
    public function testGetDomainCollectionTreatsCustomDomainsAsStorefront(): void
    {
        $storefront = self::file('de-DE.json', 'storefront');
        $custom = self::file('de-DE.json', 'swag-cms-extensions');
        $messages = self::file('de-DE.base.json', 'messages');

        $struct = new LintedTranslationFileStruct(new TranslationFileCollection([$storefront, $custom, $messages]));

        static::assertSame(
            [$storefront, $custom],
            array_values($struct->getDomainCollection('storefront')->getElements())
        );
        static::assertSame(
            [$messages],
            array_values($struct->getDomainCollection('messages')->getElements())
        );
    }

    public function testCompleteAndSpecificCollectionsReturnTheConstructorArguments(): void
    {
        $complete = new TranslationFileCollection([self::file('de-DE.json', 'storefront')]);
        $specific = new TranslationFileCollection([self::file('de-AT.json', 'storefront')]);

        $struct = new LintedTranslationFileStruct($complete, $specific);

        static::assertSame($complete, $struct->getCompleteCollection());
        static::assertSame($specific, $struct->getSpecificCollection());
    }

    public function testFixableAndFixingCollectionsCollectAddedFiles(): void
    {
        $struct = new LintedTranslationFileStruct();

        $fixable = self::file('de.json', 'storefront');
        $fixed = self::file('en.json', 'storefront');

        $struct->addFixableFile($fixable);
        $struct->addToFixingCollection($fixed);

        static::assertSame([$fixable], array_values($struct->getFixableFiles()->getElements()));
        static::assertSame([$fixed], array_values($struct->getFixingCollection()->getElements()));
    }

    private static function file(string $filename, string $domain): TranslationFile
    {
        return new TranslationFile(
            filename: $filename,
            path: 'path/to/file',
            domain: $domain,
            locale: 'de-DE',
            language: 'de',
        );
    }
}
