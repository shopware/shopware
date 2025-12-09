<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\Api\ConsentController;
use Shopware\Core\System\Consent\ConsentState;
use Shopware\Core\System\Consent\DTO\ConsentStateDTO;
use Shopware\Core\System\Consent\Service\ConsentService;
use Symfony\Component\HttpFoundation\JsonResponse;
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
            new ConsentStateDTO('consent-1', $userId, ConsentState::ACCEPTED),
            new ConsentStateDTO('consent-2', $userId, ConsentState::REQUESTED),
        ];

        $this->consentService
            ->expects($this->once())
            ->method('list')
            ->with($userId)
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

        static::assertIsArray($content[1]);
        static::assertArrayHasKey('name', $content[1]);
        static::assertArrayHasKey('identifier', $content[1]);
        static::assertArrayHasKey('status', $content[1]);
        static::assertSame('consent-2', $content[1]['name']);
        static::assertSame($userId, $content[1]['identifier']);
        static::assertSame('requested', $content[1]['status']);
    }

    public function testFetchConsentsThrowsExceptionWhenSourceIsNotAdminApiSource(): void
    {
        $context = Context::createDefaultContext();

        $this->expectException(ApiException::class);

        $this->controller->fetchConsents($context);
    }

    public function testFetchConsentsThrowsExceptionWhenUserIdIsNull(): void
    {
        $source = new AdminApiSource(null);
        $context = new Context($source);

        $this->expectException(ApiException::class);

        $this->controller->fetchConsents($context);
    }

    public function testAcceptConsent(): void
    {
        $userId = 'user-456';
        $source = new AdminApiSource($userId);
        $context = new Context($source);

        $this->consentService
            ->expects($this->once())
            ->method('acceptConsent')
            ->with('test-consent', $userId);

        $response = $this->controller->acceptConsent($context, 'test-consent');

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $content = json_decode($response->getContent() ?: '', true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([], $content);
    }

    public function testAcceptConsentThrowsExceptionWhenSourceIsNotAdminApiSource(): void
    {
        $context = Context::createDefaultContext();

        $this->expectException(ApiException::class);

        $this->controller->acceptConsent($context, 'test-consent');
    }

    public function testAcceptConsentThrowsExceptionWhenUserIdIsNull(): void
    {
        $source = new AdminApiSource(null);
        $context = new Context($source);

        $this->expectException(ApiException::class);

        $this->controller->acceptConsent($context, 'test-consent');
    }

    public function testRevokeConsent(): void
    {
        $userId = 'user-789';
        $source = new AdminApiSource($userId);
        $context = new Context($source);

        $this->consentService
            ->expects($this->once())
            ->method('revokeConsent')
            ->with('test-consent', $userId);

        $response = $this->controller->revokeConsent($context, 'test-consent');

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $content = json_decode($response->getContent() ?: '', true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([], $content);
    }

    public function testRevokeConsentThrowsExceptionWhenSourceIsNotAdminApiSource(): void
    {
        $context = Context::createDefaultContext();

        $this->expectException(ApiException::class);

        $this->controller->revokeConsent($context, 'test-consent');
    }

    public function testRevokeConsentThrowsExceptionWhenUserIdIsNull(): void
    {
        $source = new AdminApiSource(null);
        $context = new Context($source);

        $this->expectException(ApiException::class);

        $this->controller->revokeConsent($context, 'test-consent');
    }
}
