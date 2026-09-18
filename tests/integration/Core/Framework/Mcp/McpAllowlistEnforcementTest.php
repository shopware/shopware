<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * End-to-end enforcement of the per-principal MCP allowlist against real `user` and `integration`
 * rows and the live `/api/_mcp` endpoint.
 *
 * The unit tests around `McpAllowlistProvider` stub the `Connection`, so they prove the parsing
 * rules but never that the right columns are read, that `user.admin` round-trips from the database,
 * or that the endpoint actually rejects a call.
 *
 * `resources/list` and `prompts/list` are the probe, because they are not subject to the
 * progressive tool disclosure that makes `tools/list` advertise only meta-tools on a fresh session.
 *
 * Note that resources are allowlisted by URI (`shopware://currencies`) but listed by name
 * (`shopware-currencies`). Tools and prompts use their name for both.
 *
 * @internal
 */
#[Package('framework')]
class McpAllowlistEnforcementTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    public function testAdministratorUserWithoutAllowlistKeepsEveryCapability(): void
    {
        $browser = $this->getBrowser();

        static::assertContains('shopware-entity-list', $this->list($browser, 'resources/list', 'resources'));
        static::assertContains('shopware-context', $this->list($browser, 'prompts/list', 'prompts'));
    }

    public function testAdministratorUserKeepsEveryCapabilityEvenWithARestrictiveAllowlist(): void
    {
        $browser = $this->getBrowser();
        $this->setUserAllowlist($browser, ['tools' => [], 'resources' => [], 'prompts' => []]);

        // The administrator bypass is resolved before the stored value is read at all.
        static::assertContains('shopware-entity-list', $this->list($browser, 'resources/list', 'resources'));
        static::assertContains('shopware-context', $this->list($browser, 'prompts/list', 'prompts'));
    }

    public function testNonAdminUserWithoutAllowlistGetsNoCapabilities(): void
    {
        $browser = $this->nonAdminBrowser();

        static::assertNull($this->allowlistColumn('user', $this->userId($browser)), 'precondition: no allowlist');

        static::assertSame([], $this->list($browser, 'resources/list', 'resources'));
        static::assertSame([], $this->list($browser, 'prompts/list', 'prompts'));
    }

    public function testNonAdminUserGetsExactlyTheSelectedCapabilities(): void
    {
        $browser = $this->nonAdminBrowser();
        $this->setUserAllowlist($browser, [
            'tools' => [],
            'resources' => ['shopware://entities'],
            'prompts' => ['shopware-context'],
        ]);

        static::assertSame(['shopware-entity-list'], $this->list($browser, 'resources/list', 'resources'));
        static::assertSame(['shopware-context'], $this->list($browser, 'prompts/list', 'prompts'));
    }

    public function testNonAdminUserWithANullPerTypeEntryGetsNothingOfThatType(): void
    {
        $browser = $this->nonAdminBrowser();
        $this->setUserAllowlist($browser, [
            'resources' => ['shopware://entities'],
            'prompts' => null,
        ]);

        static::assertSame(['shopware-entity-list'], $this->list($browser, 'resources/list', 'resources'));
        static::assertSame([], $this->list($browser, 'prompts/list', 'prompts'));
    }

    public function testIntegrationWithoutAllowlistGetsNoCapabilities(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration();

        static::assertNull($this->allowlistColumn('integration', $this->integrationId($browser)), 'precondition: no allowlist');

        static::assertSame([], $this->list($browser, 'resources/list', 'resources'));
        static::assertSame([], $this->list($browser, 'prompts/list', 'prompts'));
    }

    public function testAdminIntegrationWithoutAllowlistAlsoGetsNoCapabilities(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration();
        $this->connection()->update('integration', ['admin' => 1], ['id' => $this->integrationId($browser)]);

        // The administrator bypass belongs to a user, not to the `admin` flag as such.
        static::assertSame([], $this->list($browser, 'resources/list', 'resources'));
        static::assertSame([], $this->list($browser, 'prompts/list', 'prompts'));
    }

    public function testIntegrationGetsExactlyTheSelectedCapabilities(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration();
        $this->setIntegrationAllowlist($browser, [
            'tools' => [],
            'resources' => ['shopware://currencies'],
            'prompts' => [],
        ]);

        static::assertSame(['shopware-currencies'], $this->list($browser, 'resources/list', 'resources'));
        static::assertSame([], $this->list($browser, 'prompts/list', 'prompts'));
    }

    public function testToolCallIsRejectedForAPrincipalWithoutAllowlist(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration();

        $response = $this->send($browser, $this->initialize($browser), 'tools/call', [
            'name' => 'shopware-entity-schema',
            'arguments' => ['entity' => 'product'],
        ]);

        static::assertArrayNotHasKey('result', $response, 'the tool call should not have reached the tool');
        static::assertArrayHasKey('error', $response);
        static::assertStringContainsString('not enabled in your MCP allowlist', (string) ($response['error']['message'] ?? ''));
    }

    public function testDiscoveryMetaToolsStayAdvertisedForAPrincipalWithoutAllowlist(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration();

        $tools = $this->list($browser, 'tools/list', 'tools');
        sort($tools);

        static::assertSame(
            ['shopware-tool-search', 'shopware-toolset-enable', 'shopware-toolsets-list'],
            $tools,
        );
    }

    /**
     * Demotes the generated administrator instead of asking AdminApiTestBehaviour for a
     * privilege-scoped user: that path derives the user's e-mail from the privilege list, so two
     * tests wanting the same privileges collide on `uniq.user.email`. The allowlist is resolved per
     * request from the `admin` column, so flipping it after the token was issued is enough.
     */
    private function nonAdminBrowser(): KernelBrowser
    {
        $browser = $this->getBrowser();

        $this->connection()->update('user', ['admin' => 0], ['id' => $this->userId($browser)]);

        return $browser;
    }

    /**
     * @param array<string, list<string>|null> $allowlist
     */
    private function setUserAllowlist(KernelBrowser $browser, array $allowlist): void
    {
        $this->connection()->update(
            'user',
            ['mcp_allowlist' => json_encode($allowlist, \JSON_THROW_ON_ERROR)],
            ['id' => $this->userId($browser)],
        );
    }

    /**
     * @param array<string, list<string>|null> $allowlist
     */
    private function setIntegrationAllowlist(KernelBrowser $browser, array $allowlist): void
    {
        $this->connection()->update(
            'integration',
            ['mcp_allowlist' => json_encode($allowlist, \JSON_THROW_ON_ERROR)],
            ['id' => $this->integrationId($browser)],
        );
    }

    private function allowlistColumn(string $table, string $id): ?string
    {
        $value = $this->connection()->fetchOne(
            \sprintf('SELECT `mcp_allowlist` FROM `%s` WHERE `id` = :id', $table),
            ['id' => $id],
        );

        return \is_string($value) ? $value : null;
    }

    private function userId(KernelBrowser $browser): string
    {
        $context = $browser->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);

        $source = $context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);

        $userId = $source->getUserId();
        static::assertIsString($userId);

        // AdminApiTestBehaviour seeds the source with the raw binary id, not the hex form.
        return \strlen($userId) === 16 ? $userId : Uuid::fromHexToBytes($userId);
    }

    private function integrationId(KernelBrowser $browser): string
    {
        $id = $browser->getServerParameter('_integration_id');
        static::assertIsString($id);

        return $id;
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    /**
     * @return list<string>
     */
    private function list(KernelBrowser $browser, string $method, string $resultKey): array
    {
        $response = $this->send($browser, $this->initialize($browser), $method, new \stdClass());

        static::assertArrayHasKey('result', $response, 'MCP response missing result: ' . json_encode($response, \JSON_THROW_ON_ERROR));

        $items = $response['result'][$resultKey] ?? [];
        static::assertIsArray($items);

        return array_values(array_column($items, 'name'));
    }

    /**
     * Every non-initialize request needs the session id the handshake mints.
     */
    private function initialize(KernelBrowser $browser): string
    {
        $this->request($browser, null, 'initialize', [
            'protocolVersion' => '2025-03-26',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'mcp-allowlist-test', 'version' => '1.0'],
        ]);

        $sessionId = $browser->getResponse()->headers->get('Mcp-Session-Id');
        static::assertIsString($sessionId, 'initialize did not return an MCP session id');

        return $sessionId;
    }

    /**
     * @param array<string, mixed>|\stdClass $params
     *
     * @return array<string, mixed>
     */
    private function send(KernelBrowser $browser, string $sessionId, string $method, array|\stdClass $params): array
    {
        $this->request($browser, $sessionId, $method, $params);

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content, 'MCP response was empty');

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<string, mixed>|\stdClass $params
     */
    private function request(KernelBrowser $browser, ?string $sessionId, string $method, array|\stdClass $params): void
    {
        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_MCP_SESSION_ID' => $sessionId,
            ]),
            json_encode([
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        static::assertSame(
            200,
            $browser->getResponse()->getStatusCode(),
            \sprintf('MCP "%s" returned non-200: %s', $method, (string) $browser->getResponse()->getContent()),
        );
    }
}
