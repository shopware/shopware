<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Agent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Agent\ExperienceStudioAgentController;
use Shopware\Core\Framework\ContentSystem\Agent\OpenAiLayoutAgentClient;
use Shopware\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ExperienceStudioAgentController::class)]
class ExperienceStudioAgentControllerTest extends TestCase
{
    public function testReturnsServiceUnavailableWhenClientIsNotConfigured(): void
    {
        $client = $this->createStub(OpenAiLayoutAgentClient::class);
        $client->method('isConfigured')->willReturn(false);
        $controller = new ExperienceStudioAgentController($client);

        $response = Feature::fake(['MCP_SERVER'], static fn () => $controller->turn(Request::create(
            '/api/_action/experience-studio-agent/turn',
            Request::METHOD_POST,
            content: '{"prompt":"Create a section","layout":[]}',
        )));

        static::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }

    public function testRejectsEmptyPrompt(): void
    {
        $client = $this->createStub(OpenAiLayoutAgentClient::class);
        $client->method('isConfigured')->willReturn(true);
        $controller = new ExperienceStudioAgentController($client);

        $response = Feature::fake(['MCP_SERVER'], static fn () => $controller->turn(Request::create(
            '/api/_action/experience-studio-agent/turn',
            Request::METHOD_POST,
            content: '{"prompt":"  ","layout":[]}',
        )));

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
