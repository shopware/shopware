<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Locale;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\Locale\LocaleException;

/**
 * @internal
 */
#[CoversClass(LanguageLocaleCodeProvider::class)]
class LanguageLocaleCodeProviderTest extends TestCase
{
    private LanguageLocaleCodeProvider $languageLocaleProvider;

    private TestDataCollection $ids;

    private MockObject&LanguageLoaderInterface $languageLoader;

    protected function setUp(): void
    {
        $this->languageLoader = $this->createMock(LanguageLoaderInterface::class);
        $this->languageLocaleProvider = new LanguageLocaleCodeProvider($this->languageLoader);
        $this->ids = new TestDataCollection();
    }

    public function testGetLocaleForLanguageId(): void
    {
        $this->languageLoader->expects(static::once())->method('loadLanguages')->willReturn($this->createData());

        static::assertEquals('en-GB', $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-en')));
        static::assertEquals('de-DE', $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-de')));
        static::assertEquals('parent-locale', $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-child')));
    }

    public function testGetLocaleForLanguageIdThrowsWhenLanguageIsNotFound(): void
    {
        static::expectException(LocaleException::class);
        $this->languageLoader->expects(static::once())->method('loadLanguages')->willReturn($this->createData());

        $this->languageLocaleProvider->getLocaleForLanguageId(Uuid::randomHex() . 'do_not_find_me');
    }

    public function testGetLocaleForLanguageIdThrowsWhenLoaderIsReset(): void
    {
        $this->languageLoader
            ->expects(static::exactly(2))
            ->method('loadLanguages')
            ->willReturn($this->createData());

        $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-en'));
        $this->languageLocaleProvider->reset();
        $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-en'));
    }

    public function testGetLocalesForLanguageIds(): void
    {
        $this->languageLoader->expects(static::once())->method('loadLanguages')->willReturn($this->createData());

        static::assertEquals([
            $this->ids->get('language-en') => 'en-GB',
            $this->ids->get('language-de') => 'de-DE',
            $this->ids->get('language-parent') => 'parent-locale',
            $this->ids->get('language-child') => 'parent-locale',
        ], $this->languageLocaleProvider->getLocalesForLanguageIds([
            $this->ids->get('language-en'),
            $this->ids->get('language-de'),
            $this->ids->get('language-parent'),
            $this->ids->get('language-child'),
        ]));
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function createData(): array
    {
        return FetchModeHelper::groupUnique([
            [
                'array_key' => $this->ids->create('language-de'),
                'id' => $this->ids->get('language-de'),
                'code' => 'de-DE',
                'parentId' => 'parentId',
                'parentCode' => 'de-DE',
            ],
            [
                'array_key' => $this->ids->create('language-en'),
                'id' => $this->ids->get('language-en'),
                'code' => 'en-GB',
                'parentId' => 'parentId',
                'parentCode' => 'de-DE',
            ],
            [
                'array_key' => $this->ids->create('language-parent'),
                'id' => $this->ids->get('language-parent'),
                'code' => 'parent-locale',
                'parentId' => 'parentId',
                'parentCode' => null,
            ],
            [
                'array_key' => $this->ids->create('language-child'),
                'id' => $this->ids->get('language-child'),
                'code' => null,
                'parentId' => $this->ids->get('language-parent'),
                'parentCode' => 'parent-code',
            ],
        ]);
    }
}
