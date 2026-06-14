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
        $actor = 'user-123';
        $source = new AdminApiSource('user-id');
        $context = new Context($source);

        $consents = [
            new ConsentState('consent-1', ConsentScope\AdminUser::NAME, $actor, ConsentStatus::ACCEPTED, $actor, '2025-12-31 23:59:59.0', '1.0.0', '1.1.0'),
            new ConsentState('consent-2', ConsentScope\System::NAME, 'system', ConsentStatus::UNSET, null, null),
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
        static::assertSame($actor, $content[0]['identifier']);
        static::assertSame('accepted', $content[0]['status']);
        static::assertSame('user-123', $content[0]['actor']);
        static::assertSame('2025-12-31 23:59:59.0', $content[0]['updatedAt']);
        static::assertSame('1.0.0', $content[0]['acceptedRevision']);
        static::assertSame('1.1.0', $content[0]['latestRevision']);

        static::assertIsArray($content[1]);
        static::assertArrayHasKey('name', $content[1]);
        static::assertArrayHasKey('identifier', $content[1]);
        static::assertArrayHasKey('status', $content[1]);
        static::assertSame('consent-2', $content[1]['name']);
        static::assertSame('system', $content[1]['identifier']);
        static::assertSame('unset', $content[1]['status']);
        static::assertNull($content[1]['actor']);
        static::assertNull($content[1]['updatedAt']);
        static::assertArrayHasKey('acceptedRevision', $content[1]);
        static::assertArrayHasKey('latestRevision', $content[1]);
    }

    public function testAcceptConsent(): void
    {
        $user = 'user-456';
        $source = new AdminApiSource('user-id');
        $context = new Context($source);

        $this->consentService
            ->expects($this->once())
            ->method('acceptConsent')
            ->with('test-consent', $context, '2026-02-01')
            ->willReturn(new ConsentState(
                'test-consent',
                ConsentScope\AdminUser::NAME,
                $user,
                ConsentStatus::ACCEPTED,
                $user,
                '2026-01-20 12:00:00.0',
                '2026-02-01',
                '2026-02-02',
            ));

        $request = new Request(request: ['consent' => 'test-consent', 'revision' => '2026-02-01']);

        $response = $this->controller->acceptConsent($context, $request);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);

        static::assertArrayIsEqualToArrayIgnoringListOfKeys([
            'name' => 'test-consent',
            'scopeName' => 'admin_user',
            'identifier' => $user,
            'status' => 'accepted',
            'actor' => $user,
            'updatedAt' => '2026-01-20 12:00:00.0',
            'acceptedRevision' => '2026-02-01',
            'latestRevision' => '2026-02-02',
        ], \json_decode($content, true, flags: \JSON_THROW_ON_ERROR), ['acceptedUntil']);
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
            ->willReturn(new ConsentState(
                'test-consent',
                ConsentScope\AdminUser::NAME,
                $userId,
                ConsentStatus::REVOKED,
                $userId,
                '2026-01-20 12:00:00.0'
            ));

        $request = new Request(request: ['consent' => 'test-consent']);

        $response = $this->controller->revokeConsent($context, $request);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);

        static::assertEquals([
            'name' => 'test-consent',
            'scopeName' => 'admin_user',
            'identifier' => $userId,
            'status' => 'revoked',
            'actor' => $userId,
            'updatedAt' => '2026-01-20 12:00:00.0',
            'acceptedRevision' => null,
            'latestRevision' => null,
            'acceptedUntil' => '2026-01-20 12:00:00.0',
        ], \json_decode($content, true, flags: \JSON_THROW_ON_ERROR));
    }
}
