<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Agent;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ExperienceStudioAgentController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly OpenAiLayoutAgentClient $client,
    ) {
    }

    #[Route(
        path: '/api/_action/experience-studio-agent/turn',
        name: 'api.action.experience_studio_agent.turn',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']],
        methods: [Request::METHOD_POST],
    )]
    public function turn(Request $request): JsonResponse
    {
        if (!Feature::isActive('MCP_SERVER')) {
            return new JsonResponse(['errors' => [['detail' => 'The MCP_SERVER feature flag must be enabled.']]], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!$this->client->isConfigured()) {
            return new JsonResponse(['errors' => [['detail' => 'The Experience Studio agent is not configured.']]], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $payload = null;
        }
        if (!\is_array($payload) || !\is_string($payload['prompt'] ?? null) || trim($payload['prompt']) === '') {
            return new JsonResponse(['errors' => [['detail' => 'A prompt is required.']]], JsonResponse::HTTP_BAD_REQUEST);
        }

        $messages = \is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
        $lastMessage = end($messages);
        if (!\is_array($lastMessage) || ($lastMessage['role'] ?? null) !== 'user' || ($lastMessage['content'] ?? null) !== $payload['prompt']) {
            $messages[] = ['role' => 'user', 'content' => $payload['prompt']];
        }
        $layout = \is_array($payload['layout'] ?? null) ? $payload['layout'] : [];

        $response = $this->client->respond(
            $messages,
            $layout,
            \is_string($payload['rootSource'] ?? null) ? $payload['rootSource'] : null,
            \is_string($payload['selectedElementId'] ?? null) ? $payload['selectedElementId'] : null,
            \is_array($payload['elementTypes'] ?? null) ? $payload['elementTypes'] : [],
            \is_array($payload['styleOptions'] ?? null) ? $payload['styleOptions'] : [],
        );

        return new JsonResponse($response);
    }
}
