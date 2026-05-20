<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Integration\IntegrationCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Saves the per-integration MCP allowlist (tools, resources, prompts).
 * Requires the `integration_mcp.editor` admin ACL privilege.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class IntegrationMcpAllowlistController
{
    /**
     * @internal
     *
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     */
    public function __construct(
        private readonly EntityRepository $integrationRepository,
    ) {
    }

    #[Route(
        path: '/api/_action/integration/{integrationId}/mcp-allowlist',
        name: 'api.action.integration.mcp-allowlist',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['api_action_integration_mcp-allowlist'],
        ],
        methods: ['POST'],
    )]
    public function save(string $integrationId, Request $request, Context $context): Response
    {
        if (!Feature::isActive('MCP_SERVER')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $integration = $this->integrationRepository
            ->search(new Criteria([$integrationId]), $context)
            ->getEntities()
            ->first();

        if ($integration === null) {
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

        $this->integrationRepository->update([
            ['id' => $integrationId, 'mcpAllowlist' => $allowlist],
        ], $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<mixed> $allowlist
     */
    private function isValidAllowlist(array $allowlist): bool
    {
        $knownKeys = [McpAllowlistProvider::TOOLS, McpAllowlistProvider::RESOURCES, McpAllowlistProvider::PROMPTS];

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
