<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Session;

use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpPrincipalResolver;
use Shopware\Core\Framework\Mcp\Session\McpSessionPrincipalBinding;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpSessionPrincipalBinding::class)]
class McpSessionPrincipalBindingTest extends TestCase
{
    private const OWNER = '0190a1b2c3d4e5f6a7b8c9d0e1f2a3b4';
    private const OTHER = '0190a1b2c3d4e5f6a7b8c9d0e1f2a3b5';

    private InMemorySessionStore $store;

    private McpSessionPrincipalBinding $binding;

    protected function setUp(): void
    {
        $this->store = new InMemorySessionStore();
        $this->binding = new McpSessionPrincipalBinding($this->store, new McpPrincipalResolver());
    }

    public function testBindKeepsExistingSessionDataAndAcceptsTheSamePrincipal(): void
    {
        $sessionId = $this->createSession(['_mcp' => ['initialized' => true]]);

        $this->binding->bind($this->request(self::OWNER), $sessionId);

        $data = Json::decodeToArray((string) $this->store->read(Uuid::fromString($sessionId)));
        static::assertSame(['initialized' => true], $data['_mcp']);
        static::assertSame('user-' . self::OWNER, $data['shopware_principal']);
        static::assertFalse($this->binding->rejects($this->request(self::OWNER, $sessionId)));
    }

    public function testRejectsAnotherPrincipal(): void
    {
        $sessionId = $this->createSession();
        $this->binding->bind($this->request(self::OWNER), $sessionId);

        static::assertTrue($this->binding->rejects($this->request(self::OTHER, $sessionId)));
        static::assertTrue($this->binding->rejects($this->request(null, $sessionId)));
    }

    public function testRejectsUnknownSessionAndSessionWithoutPrincipal(): void
    {
        static::assertTrue($this->binding->rejects($this->request(self::OWNER, Uuid::v4()->toRfc4122())));
        static::assertTrue($this->binding->rejects($this->request(self::OWNER, $this->createSession())));
        static::assertTrue($this->binding->rejects($this->request(self::OWNER, $this->createSession(raw: 'not json'))));
    }

    public function testIgnoresRequestsWithoutSession(): void
    {
        static::assertFalse($this->binding->rejects($this->request(self::OWNER)));
        static::assertFalse($this->binding->rejects($this->request(self::OWNER, 'not-a-uuid')));

        $options = $this->request(self::OTHER, $this->createSession());
        $options->setMethod(Request::METHOD_OPTIONS);
        static::assertFalse($this->binding->rejects($options));
    }

    public function testRejectsDeleteOfAnotherPrincipal(): void
    {
        $sessionId = $this->createSession();
        $this->binding->bind($this->request(self::OWNER), $sessionId);

        $delete = $this->request(self::OTHER, $sessionId);
        $delete->setMethod(Request::METHOD_DELETE);

        static::assertTrue($this->binding->rejects($delete));
    }

    public function testIgnoresStatelessEraRequests(): void
    {
        $body = Json::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => ['_meta' => [RequestMeta::PROTOCOL_VERSION => '2026-07-28']],
        ]);

        static::assertFalse($this->binding->rejects($this->request(self::OTHER, Uuid::v4()->toRfc4122(), $body)));
    }

    public function testDoesNothingWithoutSessionStore(): void
    {
        $binding = new McpSessionPrincipalBinding(null, new McpPrincipalResolver());

        static::assertFalse($binding->rejects($this->request(self::OTHER, Uuid::v4()->toRfc4122())));
        $binding->bind($this->request(self::OWNER), Uuid::v4()->toRfc4122());
    }

    public function testBindSkipsUnknownSessionAndMissingPrincipal(): void
    {
        $unknown = Uuid::v4();
        $this->binding->bind($this->request(self::OWNER), $unknown->toRfc4122());
        static::assertFalse($this->store->exists($unknown));

        $sessionId = $this->createSession();
        $this->binding->bind($this->request(null), $sessionId);
        static::assertSame('[]', $this->store->read(Uuid::fromString($sessionId)));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createSession(array $data = [], ?string $raw = null): string
    {
        $uuid = Uuid::v4();
        $this->store->write($uuid, $raw ?? Json::encode($data));

        return $uuid->toRfc4122();
    }

    private function request(?string $userId, ?string $sessionId = null, string $body = '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'): Request
    {
        $request = new Request(content: $body);
        $request->setMethod(Request::METHOD_POST);

        if ($userId !== null) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID, $userId);
        }

        if ($sessionId !== null) {
            $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, $sessionId);
        }

        return $request;
    }
}
