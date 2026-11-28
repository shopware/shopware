<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteContext::class)]
class WriteContextTest extends TestCase
{
    private const CHILD_LANGUAGE_ID = '0198b2f2d8b07a1a9c4c6c2a3f5d7e10';

    private const OTHER_ROOT_LANGUAGE_ID = '0198b2f2d8b07a1a9c4c6c2a3f5d7e20';

    private const OTHER_CHILD_LANGUAGE_ID = '0198b2f2d8b07a1a9c4c6c2a3f5d7e30';

    private WriteContext $writeContext;

    protected function setUp(): void
    {
        $this->writeContext = WriteContext::createFromContext(Context::createDefaultContext());
    }

    public function testLocaleCodeResolvesToSystemLanguageWhenChildLanguageSharesItsLocale(): void
    {
        $this->writeContext->setLanguages([
            Defaults::LANGUAGE_SYSTEM => self::language(id: Defaults::LANGUAGE_SYSTEM, code: 'de-DE'),
            self::CHILD_LANGUAGE_ID => self::language(id: self::CHILD_LANGUAGE_ID, code: 'de-DE', parentId: Defaults::LANGUAGE_SYSTEM),
        ]);

        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->writeContext->getLanguageId('de-DE'));
    }

    public function testLocaleCodeResolvesToRootLanguageWhenChildLanguageSharesItsLocale(): void
    {
        $this->writeContext->setLanguages([
            Defaults::LANGUAGE_SYSTEM => self::language(id: Defaults::LANGUAGE_SYSTEM, code: 'en-GB'),
            self::OTHER_ROOT_LANGUAGE_ID => self::language(id: self::OTHER_ROOT_LANGUAGE_ID, code: 'de-DE'),
            self::OTHER_CHILD_LANGUAGE_ID => self::language(id: self::OTHER_CHILD_LANGUAGE_ID, code: 'de-DE', parentId: self::OTHER_ROOT_LANGUAGE_ID),
        ]);

        static::assertSame(self::OTHER_ROOT_LANGUAGE_ID, $this->writeContext->getLanguageId('de-DE'));
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->writeContext->getLanguageId('en-GB'));
    }

    public function testLocaleCodeOfChildLanguageWithOwnLocaleResolvesToChildLanguage(): void
    {
        $this->writeContext->setLanguages([
            Defaults::LANGUAGE_SYSTEM => self::language(id: Defaults::LANGUAGE_SYSTEM, code: 'de-DE'),
            self::CHILD_LANGUAGE_ID => self::language(id: self::CHILD_LANGUAGE_ID, code: 'de-CH', parentId: Defaults::LANGUAGE_SYSTEM),
        ]);

        static::assertSame(self::CHILD_LANGUAGE_ID, $this->writeContext->getLanguageId('de-CH'));
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->writeContext->getLanguageId('de-DE'));
    }

    /**
     * @return array{id: string, code: string, parentId: ?string}
     */
    private static function language(string $id, string $code, ?string $parentId = null): array
    {
        return ['id' => $id, 'code' => $code, 'parentId' => $parentId];
    }
}
