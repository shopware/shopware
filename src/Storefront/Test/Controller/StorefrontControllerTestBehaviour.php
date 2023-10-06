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

trait StorefrontControllerTestBehaviour
{
    /**
     * @param array<string, mixed> $data
     */
    public function request(string $method, string $path, array $data): Response
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request($method, EnvironmentHelper::getVariable('APP_URL') . '/' . $path, $data);

        return $browser->getResponse();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function tokenize(string $route, array $data): array
    {
        $requestStack = new RequestStack();
        $request = new Request();
        /** @var Session $session */
        $session = $this->getSession();
        $request->setSession($session);
        $requestStack->push($request);

        return $data;
    }

    abstract protected static function getKernel(): KernelInterface;

    abstract protected static function getContainer(): ContainerInterface;
}
