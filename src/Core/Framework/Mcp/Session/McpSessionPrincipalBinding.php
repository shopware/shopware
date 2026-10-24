<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Session;

use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server\Wire\InboundClassifier;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpPrincipalResolver;
use Shopware\Core\Framework\Util\Exception\JsonDecodingException;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Binds a handshake-era MCP session to the principal that sent `initialize`. A later request that
 * names the session must come from the same principal; otherwise it is answered like an unknown
 * session, so the answer does not reveal that the session exists. The principal is stored in the
 * session data the SDK already persists, next to the list versions of `McpListChangedNotifier`.
 *
 * Stateless-era requests carry no session and pass untouched.
 * See decision 6 of `adr/2026-10-21-mcp-oauth-client-authentication.md`.
 */
#[Package('framework')]
class McpSessionPrincipalBinding
{
    private const SESSION_PRINCIPAL = 'shopware_principal';

    public function __construct(
        private readonly ?SessionStoreInterface $sessionStore,
        private readonly McpPrincipalResolver $principalResolver,
    ) {
    }

    /**
     * True when the request names a session that does not exist, carries no principal (opened before
     * the binding existed), or belongs to another principal. All three get the same answer.
     */
    public function rejects(Request $request): bool
    {
        if ($this->sessionStore === null || $request->isMethod(Request::METHOD_OPTIONS)) {
            return false;
        }

        $sessionId = $request->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID) ?? '';
        if ($sessionId === '' || !Uuid::isValid($sessionId) || $this->isStatelessEra($request)) {
            return false;
        }

        $uuid = Uuid::fromString($sessionId);
        if (!$this->sessionStore->exists($uuid)) {
            return true;
        }

        $stored = $this->readSession($uuid)[self::SESSION_PRINCIPAL] ?? null;
        $principal = $this->principalResolver->resolve($request);

        return !\is_string($stored) || $principal === null || !hash_equals($stored, $principal);
    }

    /**
     * Stores the principal of the current request on a session that this request created. Must run
     * after the MCP SDK has persisted its in-memory session, like `McpListChangedNotifier::syncSession()`.
     */
    public function bind(Request $request, string $sessionId): void
    {
        if ($this->sessionStore === null || !Uuid::isValid($sessionId)) {
            return;
        }

        $principal = $this->principalResolver->resolve($request);
        $uuid = Uuid::fromString($sessionId);
        if ($principal === null || !$this->sessionStore->exists($uuid)) {
            return;
        }

        $sessionData = $this->readSession($uuid);
        $sessionData[self::SESSION_PRINCIPAL] = $principal;
        $this->sessionStore->write($uuid, Json::encode($sessionData));
    }

    private function isStatelessEra(Request $request): bool
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return false;
        }

        $headers = [];
        $protocolVersion = $request->headers->get(InboundClassifier::PROTOCOL_VERSION_HEADER);
        if ($protocolVersion !== null) {
            $headers[InboundClassifier::PROTOCOL_VERSION_HEADER] = $protocolVersion;
        }

        return (new InboundClassifier())->classify(Request::METHOD_POST, $request->getContent(), $headers)->modern;
    }

    /**
     * @return array<string, mixed>
     */
    private function readSession(Uuid $uuid): array
    {
        \assert($this->sessionStore !== null);

        $raw = $this->sessionStore->read($uuid);
        if ($raw === false || $raw === '') {
            return [];
        }

        try {
            return Json::decodeToArray($raw);
        } catch (JsonDecodingException) {
            return [];
        }
    }
}
