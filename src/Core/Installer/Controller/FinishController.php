<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Finish\Notifier;
use Shopware\Core\Installer\Finish\SystemLocker;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('core')]
class FinishController extends InstallerController
{
    public function __construct(
        private readonly SystemLocker $systemLocker,
        private readonly Notifier $notifier,
        private readonly Client $client,
        private readonly string $appUrl
    ) {
    }

    #[Route(path: '/installer/finish', name: 'installer.finish', methods: ['GET'])]
    public function finish(Request $request): Response
    {
        $this->systemLocker->lock();

        $additionalInformation = [
            'language' => $request->attributes->get('_locale'),
            'method' => 'installer',
        ];
        $this->notifier->doTrackEvent(Notifier::EVENT_INSTALL_FINISHED, $additionalInformation);

        $session = $request->getSession();
        /** @var array<string, string> $adminInfo */
        $adminInfo = $session->get('ADMIN_USER', []);

        $data = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'scopes' => 'write',
            'username' => $adminInfo['username'] ?? '',
            'password' => $adminInfo['password'] ?? '',
        ];

        $session->clear();

        $redirect = $this->redirect($this->appUrl . '/admin');

        try {
            $loginResponse = $this->client->post($this->appUrl . '/api/oauth/token', [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $data,
            ]);

            $data = json_decode($loginResponse->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
            $loginTokenData = [
                'access' => $data['access_token'], 'refresh' => $data['refresh_token'], 'expiry' => $data['expires_in'],
            ];
            $appUrlInfo = parse_url($this->appUrl);
            if (!$appUrlInfo) {
                return $redirect;
            }

            $redirect->headers->setCookie(
                Cookie::create('bearerAuth', json_encode($loginTokenData, \JSON_THROW_ON_ERROR), time() + $data['expires_in'], ($appUrlInfo['path'] ?? '') . '/admin', $appUrlInfo['host'], null, false)
            );
        } catch (TransferException) {
            // ignore and don't automatically log in
        }

        return $redirect;
    }
}
