<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\Api\ConsentController;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\Service\ConsentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentController::class)]
class ConsentControllerTest extends TestCase
{
    private ConsentController $controller;

    private MockObject&ConsentService $consentService;

    protected function setUp(): void
    {
        $this->consentService = $this->createMock(ConsentService::class);
        $this->controller = new ConsentController($this->consentService);
    }

    public function testFetchConsents(): void
    {
        $userId = 'user-123';
        $source = new AdminApiSource($userId);
        $context = new Context($source);

        $consents = [
            new ConsentState('consent-1', ConsentScope\AdminUser::NAME, $userId, ConsentStatus::ACCEPTED, $userId),
            new ConsentState('consent-2', ConsentScope\System::NAME, 'system', ConsentStatus::REQUESTED, null),
        ];

        $this->consentService
            ->expects($this->once())
            ->method('list')
            ->with($context)
            ->willReturn($consents);

        $response = $this->controller->fetchConsents($context);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent() ?: '', true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertCount(2, $content);

        static::assertIsArray($content[0]);
        static::assertArrayHasKey('name', $content[0]);
        static::assertArrayHasKey('identifier', $content[0]);
        static::assertArrayHasKey('status', $content[0]);
        static::assertSame('consent-1', $content[0]['name']);
        static::assertSame($userId, $content[0]['identifier']);
        static::assertSame('accepted', $content[0]['status']);
        static::assertSame('user-123', $content[0]['actorId']);

        static::assertIsArray($content[1]);
        static::assertArrayHasKey('name', $content[1]);
        static::assertArrayHasKey('identifier', $content[1]);
        static::assertArrayHasKey('status', $content[1]);
        static::assertSame('consent-2', $content[1]['name']);
        static::assertSame('system', $content[1]['identifier']);
        static::assertSame('requested', $content[1]['status']);
        static::assertNull($content[1]['actorId']);
    }

    public function testAcceptConsent(): void
    {
        $userId = 'user-456';
        $source = new AdminApiSource($userId);
        $context = new Context($source);

        $this->consentService
            ->expects($this->once())
            ->method('acceptConsent')
            ->with('test-consent', $context)
            ->willReturn(new ConsentState('test-consent', ConsentScope\AdminUser::NAME, $userId, ConsentStatus::ACCEPTED, $userId));

        $request = new Request(request: ['consent' => 'test-consent']);

        $response = $this->controller->acceptConsent($context, $request);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame([
            'name' => 'test-consent',
            'scopeName' => 'admin_user',
            'identifier' => $userId,
            'status' => 'accepted',
            'actorId' => $userId,
        ], \json_decode($content, true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testRevokeConsent(): void
    {
        $userId = 'user-789';
        $source = new AdminApiSource($userId);
        $context = new Context($source);

        $this->consentService
            ->expects($this->once())
            ->method('revokeConsent')
            ->with('test-consent', $context)
            ->willReturn(new ConsentState('test-consent', ConsentScope\AdminUser::NAME, $userId, ConsentStatus::REVOKED, $userId));

        $request = new Request(request: ['consent' => 'test-consent']);

        $response = $this->controller->revokeConsent($context, $request);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame([
            'name' => 'test-consent',
            'scopeName' => 'admin_user',
            'identifier' => $userId,
            'status' => 'revoked',
            'actorId' => $userId,
        ], \json_decode($content, true, flags: \JSON_THROW_ON_ERROR));
    }
}
