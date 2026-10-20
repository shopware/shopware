<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\DataTransfer\Language;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LanguageCollection::class)]
class LanguageCollectionTest extends TestCase
{
    public function testElementsAreKeyedByLocale(): void
    {
        $german = new Language('de-DE', 'Deutsch');
        $english = new Language('en-GB', 'English');

        $collection = new LanguageCollection([$german, $english]);

        static::assertSame($german, $collection->get('de-DE'));
        static::assertSame($english, $collection->get('en-GB'));
    }

    public function testAddKeysTheElementByLocale(): void
    {
        $collection = new LanguageCollection();
        $language = new Language('nl-NL', 'Nederlands');

        $collection->add($language);

        static::assertSame($language, $collection->get('nl-NL'));
    }

    public function testSetUsesTheGivenKey(): void
    {
        $collection = new LanguageCollection();
        $language = new Language('fr-FR', 'Français');

        $collection->set('custom-key', $language);

        static::assertSame($language, $collection->get('custom-key'));
    }
}
