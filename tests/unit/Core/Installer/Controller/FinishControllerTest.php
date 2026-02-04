<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\FinishController;
use Shopware\Core\Installer\Finish\SystemLocker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(FinishController::class)]
class FinishControllerTest extends TestCase
{
    private SystemLocker $systemLocker;
    private Client $client;

    protected function setUp(): void
    {
        $this->systemLocker = $this->createMock(SystemLocker::class);
        $this->client = $this->createMock(Client::class);
    }

    public function testFinishWithCompletedParameterRendersTemplate(): void
    {
        $controller = $this->getMockBuilder(FinishController::class)
            ->setConstructorArgs([
                $this->systemLocker,
                $this->client,
                'https://example.com',
                'admin',
            ])
            ->onlyMethods(['renderInstaller'])
            ->getMock();

        $controller
            ->expects(static::once())
            ->method('renderInstaller')
            ->with('@Installer/installer/finish.html.twig', [])
            ->willReturn(new Response('rendered'));

        $request = new Request(['completed' => '1']);

        $response = $controller->finish($request);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('rendered', $response->getContent());
    }

    public function testFinishLocksSystemAndRedirectsWithCookie(): void
    {
        $this->systemLocker
            ->expects(static::once())
            ->method('lock');

        $this->client
            ->expects(static::once())
            ->method('post')
            ->willReturn(new GuzzleResponse(
                200,
                [],
                json_encode([
                    'access_token' => 'access',
                    'refresh_token' => 'refresh',
                    'expires_in' => 3600,
                ], \JSON_THROW_ON_ERROR)
            ));

        $session = new Session(new MockArraySessionStorage());
        $session->set('ADMIN_USER', [
            'username' => 'admin',
            'password' => 'secret',
        ]);

        $request = new Request();
        $request->setSession($session);

        $controller = $this->createController(
            'https://example.com',
            'admin'
        );

        $response = $controller->finish($request);

        static::assertSame(302, $response->getStatusCode());
        static::assertSame(
            'https://example.com/admin',
            $response->headers->get('Location')
        );

        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);

        static::assertSame('bearerAuth', $cookies[0]->getName());
    }

    public function testFinishUsesCustomAdminPathNameForRedirectAndCookiePath(): void
    {
        $this->systemLocker
            ->expects(static::once())
            ->method('lock');

        $this->client
            ->expects(static::once())
            ->method('post')
            ->willReturn(new GuzzleResponse(
                200,
                [],
                json_encode([
                    'access_token' => 'access',
                    'refresh_token' => 'refresh',
                    'expires_in' => 600,
                ], \JSON_THROW_ON_ERROR)
            ));

        $session = new Session(new MockArraySessionStorage());
        $session->set('ADMIN_USER', [
            'username' => 'admin',
            'password' => 'secret',
        ]);

        $request = new Request();
        $request->setSession($session);

        $controller = $this->createController(
            'https://example.com/shop',
            'custom-admin'
        );

        $response = $controller->finish($request);

        static::assertSame(
            'https://example.com/shop/custom-admin',
            $response->headers->get('Location')
        );

        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);

        static::assertSame('/shop/custom-admin', $cookies[0]->getPath());
    }

    public function testFinishIgnoresTransferException(): void
    {
        $this->systemLocker
            ->expects(static::once())
            ->method('lock');

        $this->client
            ->expects(static::once())
            ->method('post')
            ->willThrowException(
                $this->createMock(TransferException::class)
            );

        $session = new Session(new MockArraySessionStorage());
        $session->set('ADMIN_USER', [
            'username' => 'admin',
            'password' => 'secret',
        ]);

        $request = new Request();
        $request->setSession($session);

        $controller = $this->createController(
            'https://example.com',
            'admin'
        );

        $response = $controller->finish($request);

        static::assertSame(302, $response->getStatusCode());
        static::assertCount(0, $response->headers->getCookies());
    }

    private function createController(string $appUrl, string $adminPathName): FinishController
    {
        return new FinishController(
            $this->systemLocker,
            $this->client,
            $appUrl,
            $adminPathName
        );
    }
}

