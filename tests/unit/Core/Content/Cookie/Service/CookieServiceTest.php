<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(CookieService::class)]
class CookieServiceTest extends TestCase
{
    public function testRemoveCookieGroupsWithoutCookies(): void
    {
        $otherGroup = new CookieGroup('cookie.groupOther');
        $otherGroup->setEntries(new CookieEntryCollection([new CookieEntry('other-cookie')]));

        $groupWithoutEntries = new CookieGroup('cookie.groupWithoutEntries');
        $groupWithoutEntries->setEntries(new CookieEntryCollection());

        $groupAsCookie = new CookieGroup('cookie.groupAsCookie');
        $groupAsCookie->setCookie('group-as-cookie');

        $cookieGroups = new CookieGroupCollection([$otherGroup, $groupWithoutEntries, $groupAsCookie]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(function ($key) {
                return $key; // Return the key itself when no translation is found
            });

        (new CookieService($translator))->removeCookieGroupsWithoutCookies($cookieGroups);

        static::assertCount(2, $cookieGroups);
        $group = $cookieGroups->get('cookie.groupOther');
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('cookie.groupOther', $group->snippetKeyName);
        $entries = $group->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        static::assertSame('other-cookie', $entries->get('other-cookie')?->cookie);

        $group = $cookieGroups->get('cookie.groupAsCookie');
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('cookie.groupAsCookie', $group->snippetKeyName);
        static::assertSame('group-as-cookie', $group->getCookie());
    }

    public function testGetCookieGroupCollectionWithTranslation(): void
    {
        $cookieGroupEntry = new CookieEntry('test-cookie');
        $cookieGroupEntry->snippetKeyName = 'cookie.entry.test';
        $cookieGroupEntry->snippetKeyDescription = 'cookie.entry.test.description';

        $cookieGroup = new CookieGroup('cookie.group.test');
        $cookieGroup->snippetKeyDescription = 'cookie.group.test.description';
        $cookieGroup->setEntries(new CookieEntryCollection([$cookieGroupEntry]));

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(function ($key) {
                return 'Translated: ' . $key;
            });

        $cookieGroupCollection = new CookieGroupCollection([$cookieGroup]);
        (new CookieService($translator))->translateCookieGroups($cookieGroupCollection);

        static::assertCount(1, $cookieGroupCollection);
        $group = $cookieGroupCollection->get('cookie.group.test');
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('cookie.group.test', $group->snippetKeyName);
        static::assertSame('Translated: cookie.group.test', $group->translatedName);
        static::assertSame('cookie.group.test.description', $group->snippetKeyDescription);
        static::assertSame('Translated: cookie.group.test.description', $group->translatedDescription);
        $entries = $group->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        $entry = $entries->get('test-cookie');
        static::assertNotNull($entry);
        static::assertSame('cookie.entry.test', $entry->snippetKeyName);
        static::assertSame('Translated: cookie.entry.test', $entry->translatedName);
        static::assertSame('cookie.entry.test.description', $entry->snippetKeyDescription);
        static::assertSame('Translated: cookie.entry.test.description', $entry->translatedDescription);
        static::assertSame('test-cookie', $entry->cookie);
    }
}
