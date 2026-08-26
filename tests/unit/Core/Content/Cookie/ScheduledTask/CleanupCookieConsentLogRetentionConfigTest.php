<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\System;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

/**
 * Guards the coupling between the admin settings card (cookieConsentRetention.xml) and the
 * config key the cleanup task reads: if either side is renamed without the other,
 * operator edits in the admin would silently stop affecting the cleanup.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupCookieConsentLogTaskHandler::class)]
class CleanupCookieConsentLogRetentionConfigTest extends TestCase
{
    public function testAdminConfigFieldMatchesTheKeyAndDefaultTheCleanupReads(): void
    {
        $cards = (new ConfigReader())->getConfigFromBundle(new System(), 'cookieConsentRetention');

        $retentionField = null;
        foreach ($cards as $card) {
            foreach ($card['elements'] as $element) {
                if ('core.cookieConsentRetention.' . $element['name'] === CleanupCookieConsentLogTaskHandler::CONFIG_KEY_RETENTION_DAYS) {
                    $retentionField = $element;
                }
            }
        }

        static::assertNotNull(
            $retentionField,
            \sprintf(
                'cookieConsentRetention.xml must expose the field behind "%s", otherwise the retention cannot be managed in the admin',
                CleanupCookieConsentLogTaskHandler::CONFIG_KEY_RETENTION_DAYS,
            ),
        );

        static::assertSame('int', $retentionField['type']);
        static::assertSame(CleanupCookieConsentLogTaskHandler::DEFAULT_RETENTION_DAYS, $retentionField['defaultValue']);
    }
}
