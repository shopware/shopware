<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

trait StorefrontControllerTestBehaviour
{
    public function request(string $method, string $path, array $data): Response
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request($method, getenv('APP_URL') . '/' . $path, $data);

        return $browser->getResponse();
    }

    public function tokenize(string $route, array $data): array
    {
        $token = $this->getContainer()
            ->get('security.csrf.token_manager')
            ->getToken($route)
            ->getValue();

        $data['_csrf_token'] = $token;

        return $data;
    }

    abstract protected function getKernel(): KernelInterface;

    abstract protected function getContainer(): ContainerInterface;
}
