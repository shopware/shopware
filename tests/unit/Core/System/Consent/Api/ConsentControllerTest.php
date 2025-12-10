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
use Shopware\Core\System\Consent\ConsentContext;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentState;
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
            new ConsentState('consent-1', ConsentScope::ADMIN_USER, $userId, ConsentStatus::ACCEPTED, $userId),
            new ConsentState('consent-2', ConsentScope::GLOBAL, null, ConsentStatus::REQUESTED, null),
        ];

        $this->consentService
            ->expects($this->once())
            ->method('list')
            ->with(static::callback(static function (ConsentContext $context) use ($userId): bool {
                return $context->getIdentifierForScope(ConsentScope::ADMIN_USER) === $userId;
            }))
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
        static::assertNull($content[1]['identifier']);
        static::assertSame('requested', $content[1]['status']);
        static::assertNull($content[1]['actorId']);
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
        static::assertSame('{}', $response->getContent());
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
        static::assertSame('{}', $response->getContent());
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
