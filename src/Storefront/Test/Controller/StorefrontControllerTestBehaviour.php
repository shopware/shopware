<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

trait StorefrontControllerTestBehaviour
{
    public function request(string $method, string $path, array $data): Response
    {
        /** @var Session $session */
        $session = KernelLifecycleManager::getKernel()->getContainer()->get('session');
        // Init the session service for csrf processing
        $session->all();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request($method, EnvironmentHelper::getVariable('APP_URL') . '/' . $path, $data);

        return $browser->getResponse();
    }

    public function tokenize(string $route, array $data): array
    {
        $requestStack = new RequestStack();
        $request = new Request();
        /** @var Session $session */
        $session = $this->getContainer()->get('session');
        $request->setSession($session);
        $requestStack->push($request);

        $tokenStorage = new CsrfTokenManager(
            new UriSafeTokenGenerator(),
            new SessionTokenStorage($requestStack)
        );

        $data['_csrf_token'] = $tokenStorage->getToken($route);

        return $data;
    }

    abstract protected function getKernel(): KernelInterface;

    abstract protected function getContainer(): ContainerInterface;
}
