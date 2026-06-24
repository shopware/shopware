<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class PrivacySnippetWordingTest extends TestCase
{
    public function testEnglishPrivacySnippetsStayActionNeutral(): void
    {
        $storefrontSnippets = self::readJson(__DIR__ . '/../../../../../src/Storefront/Resources/snippet/storefront.en.json');
        $adminSnippets = self::readJson(__DIR__ . '/../../../../../src/Administration/Resources/app/administration/src/module/sw-cms/snippet/en.json');

        foreach ([
            'general.privacyNoticeText' => self::snippet($storefrontSnippets, ['general', 'privacyNoticeText']),
            'general.privacyNoticeTextModal' => self::snippet($storefrontSnippets, ['general', 'privacyNoticeTextModal']),
            'general.privacyNoticeTextPage' => self::snippet($storefrontSnippets, ['general', 'privacyNoticeTextPage']),
            'contact.privacyNoticeText' => self::snippet($storefrontSnippets, ['contact', 'privacyNoticeText']),
            'contact.privacyNoticeTextModal' => self::snippet($storefrontSnippets, ['contact', 'privacyNoticeTextModal']),
            'contact.privacyNoticeTextPage' => self::snippet($storefrontSnippets, ['contact', 'privacyNoticeTextPage']),
            'sw-cms.general.privacyNotice' => self::snippet($adminSnippets, ['sw-cms', 'general', 'privacyNotice']),
        ] as $snippetKey => $snippet) {
            static::assertStringNotContainsString('selecting continue', strtolower($snippet), \sprintf('Snippet "%s" must not reference a generic continue action.', $snippetKey));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function readJson(string $path): array
    {
        $content = file_get_contents($path);
        static::assertIsString($content);

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $snippets
     * @param list<string> $path
     */
    private static function snippet(array $snippets, array $path): string
    {
        $value = $snippets;

        foreach ($path as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                static::fail(\sprintf('Snippet "%s" must exist.', implode('.', $path)));
            }

            $value = $value[$key];
        }

        static::assertIsString($value, \sprintf('Snippet "%s" must be a string.', implode('.', $path)));

        return $value;
    }
}
