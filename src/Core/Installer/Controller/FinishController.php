<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Finish\SystemLocker;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
class FinishController extends InstallerController
{
    private const COMPLETION_PARAMETER = 'completed';

    public function __construct(
        private readonly SystemLocker $systemLocker,
        private readonly Client $client,
        private readonly string $appUrl,
        private readonly ClockInterface $clock,
        private readonly string $adminPathName = 'admin',
    ) {
    }

    #[Route(path: '/installer/finish', name: 'installer.finish', methods: ['GET'])]
    public function finish(Request $request): Response
    {
        if ($request->query->has(self::COMPLETION_PARAMETER)) {
            return $this->renderInstaller('@Installer/installer/finish.html.twig');
        }

        $this->systemLocker->lock();

        $session = $request->getSession();
        /** @var array<string, string> $adminInfo */
        $adminInfo = $session->get('ADMIN_USER', []);

        $requestData = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'scopes' => 'write',
            'username' => $adminInfo['username'] ?? '',
            'password' => $adminInfo['password'] ?? '',
        ];

        $session->clear();

        $redirect = $this->redirect(\sprintf('%s/%s', $this->appUrl, $this->adminPathName));

        try {
            $loginResponse = $this->client->post($this->appUrl . '/api/oauth/token', [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $requestData,
            ]);

            $body = $loginResponse->getBody()->getContents();

            $responseData = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $loginTokenData = [
                'access' => $responseData['access_token'], 'refresh' => $responseData['refresh_token'], 'expiry' => $responseData['expires_in'],
            ];
            $appUrlInfo = parse_url($this->appUrl);
            if (!$appUrlInfo) {
                return $redirect;
            }

            $cookiePath = \sprintf('%s/%s', rtrim($appUrlInfo['path'] ?? '', '/'), $this->adminPathName);

            $redirect->headers->setCookie(
                Cookie::create(
                    'bearerAuth',
                    json_encode($loginTokenData, \JSON_THROW_ON_ERROR),
                    $this->clock->now()->getTimestamp() + $responseData['expires_in'],
                    $cookiePath,
                    $appUrlInfo['host'] ?? null,
                    httpOnly: false
                )
            );
        } catch (TransferException) {
            // ignore and don't automatically log in
        }

        return $redirect;
    }
}
