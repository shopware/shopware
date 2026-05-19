<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\FinishController;
use Shopware\Core\Installer\Finish\SystemLocker;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[CoversClass(FinishController::class)]
class FinishControllerTest extends TestCase
{
    private SystemLocker&MockObject $systemLocker;

    protected function setUp(): void
    {
        $this->systemLocker = $this->createMock(SystemLocker::class);
    }

    public function testFinishWithCompletedParameterRendersTemplate(): void
    {
        $controller = $this->getMockBuilder(FinishController::class)
            ->setConstructorArgs([
                $this->systemLocker,
                new Client(),
                'https://www.shopware.com',
                new NativeClock(),
                'admin',
            ])
            ->onlyMethods(['renderInstaller'])
            ->getMock();

        $controller
            ->expects($this->once())
            ->method('renderInstaller')
            ->with('@Installer/installer/finish.html.twig', [])
            ->willReturn(new Response('rendered'));

        $request = new Request(['completed' => '1']);

        $response = $controller->finish($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('rendered', $response->getContent());
    }

    public function testFinishLocksSystemAndRedirectsWithCookie(): void
    {
        $this->systemLocker
            ->expects($this->once())
            ->method('lock');

        $client = new Client(['handler' => new MockHandler([new GuzzleResponse(
            body: json_encode([
                'access_token' => 'access',
                'refresh_token' => 'refresh',
                'expires_in' => 3600,
            ], \JSON_THROW_ON_ERROR)
        )])]);

        $session = new Session(new MockArraySessionStorage());
        $session->set('ADMIN_USER', [
            'username' => 'admin',
            'password' => 'secret',
        ]);

        $request = new Request();
        $request->setSession($session);

        $response = $this->createController($client)->finish($request);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('https://www.shopware.com/admin', $response->headers->get('Location'));

        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);

        static::assertSame('bearerAuth', $cookies[0]->getName());
    }

    public function testFinishUsesCustomAdminPathNameForRedirectAndCookiePath(): void
    {
        $this->systemLocker
            ->expects($this->once())
            ->method('lock');

        $client = new Client(['handler' => new MockHandler([new GuzzleResponse(
            body: json_encode([
                'access_token' => 'access',
                'refresh_token' => 'refresh',
                'expires_in' => 600,
            ], \JSON_THROW_ON_ERROR)
        )])]);

        $session = new Session(new MockArraySessionStorage());
        $session->set('ADMIN_USER', [
            'username' => 'admin',
            'password' => 'secret',
        ]);

        $request = new Request();
        $request->setSession($session);

        $controller = $this->createController($client, 'https://example.com/shop', 'custom-admin');

        $response = $controller->finish($request);

        static::assertSame('https://example.com/shop/custom-admin', $response->headers->get('Location'));

        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);

        static::assertSame('/shop/custom-admin', $cookies[0]->getPath());
    }

    public function testFinishIgnoresTransferException(): void
    {
        $this->systemLocker
            ->expects($this->once())
            ->method('lock');

        $client = new Client(['handler' => new MockHandler([new TransferException()])]);

        $session = new Session(new MockArraySessionStorage());
        $session->set('ADMIN_USER', [
            'username' => 'admin',
            'password' => 'secret',
        ]);

        $request = new Request();
        $request->setSession($session);

        $response = $this->createController($client)->finish($request);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertCount(0, $response->headers->getCookies());
    }

    private function createController(
        Client $client,
        string $appUrl = 'https://www.shopware.com',
        string $adminPathName = 'admin'
    ): FinishController {
        return new FinishController(
            $this->systemLocker,
            $client,
            $appUrl,
            new NativeClock(),
            $adminPathName,
        );
    }
}
