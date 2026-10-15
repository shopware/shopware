<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * An installation-wide list change (an app install, for example) reaches an existing session without
 * a registry of sessions: the change bumps a version in the database, and the session's next request
 * notices it.
 *
 * @internal
 */
#[Package('framework')]
class McpListChangedNotificationTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM `mcp_list_version`');
    }

    public function testAnExistingSessionIsNotifiedAboutAListChange(): void
    {
        $browser = $this->getBrowser();
        $sessionId = $this->initialize($browser);

        static::assertNotContains('notifications/tools/list_changed', $this->request($browser, $sessionId, 2));

        static::getContainer()->get(McpListChangedNotifier::class)
            ->notify(new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));

        // The request after the change notices the new version and queues the notification; the
        // client receives it with the request after that, like any queued notification.
        $this->request($browser, $sessionId, 3);
        $methods = $this->request($browser, $sessionId, 4);

        static::assertContains('notifications/tools/list_changed', $methods);
        static::assertNotContains('notifications/prompts/list_changed', $methods);
        static::assertNotContains('notifications/tools/list_changed', $this->request($browser, $sessionId, 5), 'each change is delivered once');
    }

    public function testANewSessionIsNotNotifiedAboutEarlierChanges(): void
    {
        static::getContainer()->get(McpListChangedNotifier::class)
            ->notify(new McpListChangedNotificationSet(tools: true, resources: true, prompts: true));

        $browser = $this->getBrowser();
        $sessionId = $this->initialize($browser);

        $this->request($browser, $sessionId, 2);

        static::assertNotContains('notifications/tools/list_changed', $this->request($browser, $sessionId, 3));
    }

    private function initialize(KernelBrowser $browser): string
    {
        $browser->request('POST', '/api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'mcp-list-changed-test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR));

        $sessionId = $browser->getResponse()->headers->get('mcp-session-id');
        static::assertIsString($sessionId, 'initialize did not return an MCP session id');

        return $sessionId;
    }

    /**
     * @return list<string> the JSON-RPC methods in the response, which include queued notifications
     */
    private function request(KernelBrowser $browser, string $sessionId, int $id): array
    {
        $browser->request('POST', '/api/_mcp', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_SESSION_ID' => $sessionId,
        ], json_encode(['jsonrpc' => '2.0', 'method' => 'ping', 'id' => $id], \JSON_THROW_ON_ERROR));

        $content = (string) $browser->getResponse()->getContent();
        $messages = [];
        if (str_starts_with(ltrim($content), '{')) {
            $messages[] = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } else {
            foreach (explode("\n", $content) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $messages[] = json_decode(trim(substr($line, 5)), true, 512, \JSON_THROW_ON_ERROR);
                }
            }
        }

        return array_values(array_filter(array_map(
            static fn (mixed $message): ?string => \is_array($message) && \is_string($message['method'] ?? null) ? $message['method'] : null,
            $messages,
        )));
    }
}
