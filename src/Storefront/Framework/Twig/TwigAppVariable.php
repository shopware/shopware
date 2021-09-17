<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * To allow custom server parameters,
 */
class TwigAppVariable extends AppVariable
{
    private ?Request $request = null;

    private AppVariable $appVariable;

    private array $allowList;

    public function __construct(AppVariable $appVariable, array $allowList = [])
    {
        $this->allowList = $allowList;
        $this->appVariable = $appVariable;
    }

    public function getRequest()
    {
        if ($this->request !== null) {
            return $this->request;
        }

        $request = $this->appVariable->getRequest();

        if ($request === null) {
            throw new \RuntimeException('The "app.request" variable is not available.');
        }

        $clonedRequest = clone $request;

        $clonedRequest->server = clone $clonedRequest->server;

        foreach ($clonedRequest->server->all() as $key => $_) {
            if (!\in_array(strtolower($key), $this->allowList, true)) {
                $clonedRequest->server->remove($key);
            }
        }

        $this->request = $clonedRequest;

        return $clonedRequest;
    }

    public function setTokenStorage(TokenStorageInterface $tokenStorage): void
    {
        $this->appVariable->setTokenStorage($tokenStorage);
    }

    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->appVariable->setRequestStack($requestStack);
    }

    public function setEnvironment(string $environment): void
    {
        $this->appVariable->setEnvironment($environment);
    }

    public function setDebug(bool $debug): void
    {
        $this->appVariable->setDebug($debug);
    }

    public function getToken()
    {
        return $this->appVariable->getToken();
    }

    public function getUser()
    {
        return $this->appVariable->getUser();
    }

    public function getSession()
    {
        return $this->appVariable->getSession();
    }

    public function getEnvironment()
    {
        return $this->appVariable->getEnvironment();
    }

    public function getDebug()
    {
        return $this->appVariable->getDebug();
    }

    public function getFlashes($types = null)
    {
        return $this->appVariable->getFlashes($types);
    }
}
