<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cookie\CookieConsentLog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * Both tables are consent evidence. They are exposed through the Admin API so operators can
 * export them, which also generates CRUD endpoints that must not be able to change or remove
 * a decision. The value of the log is that only the consent log route ever wrote to it.
 *
 * @internal
 */
#[Package('framework')]
class CookieConsentEvidenceApiProtectionTest extends TestCase
{
    // Only what this test needs: a kernel for the container, a rolled back transaction for
    // the fixtures, and an authenticated Admin API browser.
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const LOG_ID = 'a1b2c3d4e5f6478899aabbccddeeff01';
    private const SNAPSHOT_ID = 'a1b2c3d4e5f6478899aabbccddeeff02';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->insert('cookie_consent_config_version', [
            'id' => Uuid::fromHexToBytes(self::SNAPSHOT_ID),
            'config_hash' => 'protection-test-hash',
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'cookie_groups' => '[]',
            'created_at' => '2026-07-13 12:00:00.000',
        ]);

        $connection->insert('cookie_consent_log', [
            'id' => Uuid::fromHexToBytes(self::LOG_ID),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'consent_action' => 'accept_all',
            'group_decisions' => '{"cookie.groupRequired":"accepted"}',
            'accepted_cookies' => '[]',
            'server_config_hash' => 'protection-test-hash',
            'created_at' => '2026-07-13 12:00:00.000',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('blockedWriteRequests')]
    public function testTheApiCannotChangeEvidence(string $method, string $url, array $payload): void
    {
        $this->getBrowser()->jsonRequest($method, '/api/' . $url, $payload);

        $response = $this->getBrowser()->getResponse();
        $content = (string) $response->getContent();

        static::assertContains($response->getStatusCode(), [Response::HTTP_BAD_REQUEST, Response::HTTP_FORBIDDEN], $content);

        // The row must still be there, a rejected request may not have deleted anything
        static::assertSame(
            2,
            (int) static::getContainer()->get(Connection::class)->fetchOne(
                'SELECT (SELECT COUNT(*) FROM `cookie_consent_log` WHERE `id` = :log)
                      + (SELECT COUNT(*) FROM `cookie_consent_config_version` WHERE `id` = :snapshot)',
                ['log' => Uuid::fromHexToBytes(self::LOG_ID), 'snapshot' => Uuid::fromHexToBytes(self::SNAPSHOT_ID)],
            ),
        );
    }

    /**
     * @return \Generator<string, array{method: string, url: string, payload: array<string, mixed>}>
     */
    public static function blockedWriteRequests(): \Generator
    {
        yield 'a delete cannot remove a logged decision' => [
            'method' => 'DELETE',
            'url' => 'cookie-consent-log/' . self::LOG_ID,
            'payload' => [],
        ];

        yield 'a delete cannot remove a banner snapshot' => [
            'method' => 'DELETE',
            'url' => 'cookie-consent-config-version/' . self::SNAPSHOT_ID,
            'payload' => [],
        ];

        yield 'an update cannot rewrite the recorded action' => [
            'method' => 'PATCH',
            'url' => 'cookie-consent-log/' . self::LOG_ID,
            'payload' => ['consentAction' => 'accept_required'],
        ];

        yield 'an update cannot repoint a snapshot at another configuration' => [
            'method' => 'PATCH',
            'url' => 'cookie-consent-config-version/' . self::SNAPSHOT_ID,
            'payload' => ['configHash' => 'tampered'],
        ];

        yield 'a create cannot fabricate a decision nobody made' => [
            'method' => 'POST',
            'url' => 'cookie-consent-log',
            'payload' => [
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'consentAction' => 'accept_all',
                'groupDecisions' => ['fabricated' => 'accepted'],
                'acceptedCookies' => [],
                'serverConfigHash' => 'fabricated',
            ],
        ];
    }

    #[DataProvider('allowedReadRequests')]
    public function testTheApiCanReadEvidence(string $method, string $url): void
    {
        $this->getBrowser()->jsonRequest($method, '/api/' . $url);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('protection-test-hash', (string) $response->getContent());
    }

    /**
     * @return \Generator<string, array{method: string, url: string}>
     */
    public static function allowedReadRequests(): \Generator
    {
        yield 'listing the log stays readable' => ['method' => 'GET', 'url' => 'cookie-consent-log'];
        yield 'a single log entry stays readable' => ['method' => 'GET', 'url' => 'cookie-consent-log/' . self::LOG_ID];
        yield 'searching the log stays readable' => ['method' => 'POST', 'url' => 'search/cookie-consent-log'];
        yield 'listing snapshots stays readable' => ['method' => 'GET', 'url' => 'cookie-consent-config-version'];
        yield 'searching snapshots stays readable' => ['method' => 'POST', 'url' => 'search/cookie-consent-config-version'];
    }
}
