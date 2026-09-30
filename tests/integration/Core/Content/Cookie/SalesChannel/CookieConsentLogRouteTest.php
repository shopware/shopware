<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cookie\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\DatabaseCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the route against the real cookie configuration and the database storage. The
 * storage is wired by hand because logging is off in the default configuration.
 *
 * @internal
 */
#[Package('framework')]
class CookieConsentLogRouteTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private CookieConsentLogRoute $route;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        $this->route = new CookieConsentLogRoute(
            static::getContainer()->get(CookieRoute::class),
            new DatabaseCookieConsentLogStorage($this->connection),
            new NativeClock(),
            static::createStub(RateLimiter::class),
        );

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testLogPersistsDecisionAndConfigSnapshot(): void
    {
        $this->log(['consentId' => 'visitor-a', 'consentAction' => 'accept_all']);

        $logs = $this->connection->fetchAllAssociative('SELECT * FROM `cookie_consent_log`');
        static::assertCount(1, $logs);
        static::assertSame('visitor-a', $logs[0]['consent_id']);
        static::assertSame('accept_all', $logs[0]['consent_action']);

        $groupDecisions = json_decode((string) $logs[0]['group_decisions'], true);
        static::assertIsArray($groupDecisions);
        static::assertNotEmpty($groupDecisions);
        static::assertSame(['accepted'], array_values(array_unique($groupDecisions)));

        // The banner snapshot exists for the hash the log entry references
        $snapshots = $this->connection->fetchAllAssociative('SELECT * FROM `cookie_consent_config_snapshot`');
        static::assertCount(1, $snapshots);
        static::assertSame($logs[0]['config_hash'], $snapshots[0]['config_hash']);
        static::assertJson((string) $snapshots[0]['cookie_groups']);

        // A second consent adds a log entry but no duplicate snapshot
        $this->log(['consentId' => 'visitor-b', 'consentAction' => 'accept_all']);

        static::assertCount(2, $this->connection->fetchAllAssociative('SELECT * FROM `cookie_consent_log`'));
        static::assertCount(1, $this->connection->fetchAllAssociative('SELECT * FROM `cookie_consent_config_snapshot`'));
    }

    public function testAWithdrawalIsRecordedAsASecondDecisionOfTheSameVisitor(): void
    {
        $this->log(['consentId' => 'visitor-a', 'consentAction' => 'accept_all']);
        // The visitor re-opens the banner and keeps only one of the two comfort cookies
        $this->log(['consentId' => 'visitor-a', 'consentAction' => 'accept_selected', 'acceptedCookies' => ['youtube-video']]);

        $logs = $this->connection->fetchAllAssociative('SELECT * FROM `cookie_consent_log` ORDER BY `created_at`, `id`');
        static::assertCount(2, $logs);
        static::assertSame(['visitor-a', 'visitor-a'], array_column($logs, 'consent_id'));

        $consent = json_decode((string) $logs[0]['group_decisions'], true);
        $withdrawal = json_decode((string) $logs[1]['group_decisions'], true);
        static::assertIsArray($consent);
        static::assertIsArray($withdrawal);
        static::assertSame('accepted', $consent['cookie.groupComfortFeatures']);
        static::assertSame('partial', $withdrawal['cookie.groupComfortFeatures']);
        static::assertSame('["youtube-video"]', $logs[1]['accepted_cookies']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function log(array $payload): void
    {
        $response = $this->route->log(new Request(content: (string) json_encode($payload)), $this->salesChannelContext);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
