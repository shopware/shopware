<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Saves the per-user MCP allowlist (tools, resources, prompts).
 * Requires the `users_and_permissions.editor` admin ACL privilege.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class UserMcpAllowlistController
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
    ) {
    }

    #[Route(
        path: '/api/_action/user/{userId}/mcp-allowlist',
        name: 'api.action.user.mcp-allowlist',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['api_action_user_mcp-allowlist'],
        ],
        methods: ['POST'],
    )]
    public function save(string $userId, Request $request, Context $context): Response
    {
        if (!Feature::isActive('MCP_SERVER')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $user = $this->userRepository
            ->search(new Criteria([$userId]), $context)
            ->getEntities()
            ->first();

        if ($user === null) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $body = $request->toArray();

        if (!\array_key_exists('allowlist', $body)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $allowlist = $body['allowlist'];

        if ($allowlist !== null && !\is_array($allowlist)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        if ($allowlist !== null && !$this->isValidAllowlist($allowlist)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId, $allowlist): void {
            $this->userRepository->update([
                ['id' => $userId, 'mcpAllowlist' => $allowlist],
            ], $context);
        });

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<mixed> $allowlist
     */
    private function isValidAllowlist(array $allowlist): bool
    {
        $knownKeys = [McpAllowlist::TOOLS, McpAllowlist::RESOURCES, McpAllowlist::PROMPTS];

        if (array_diff(array_keys($allowlist), $knownKeys) !== []) {
            return false;
        }

        foreach ($knownKeys as $key) {
            if (!\array_key_exists($key, $allowlist)) {
                continue;
            }
            $value = $allowlist[$key];
            if ($value !== null && !\is_array($value)) {
                return false;
            }
            if (\is_array($value) && array_filter($value, static fn ($item) => !\is_string($item)) !== []) {
                return false;
            }
        }

        return true;
    }
}
