<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * To allow custom server parameters,
 */
#[Package('core')]
class TwigAppVariable extends AppVariable
{
    private ?Request $request = null;

    /**
     * @internal
     *
     * @param list<string> $allowList
     */
    public function __construct(
        private readonly AppVariable $appVariable,
        private readonly array $allowList = []
    ) {
    }

    public function getRequest(): ?Request
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

    public function getToken(): ?TokenInterface
    {
        return $this->appVariable->getToken();
    }

    public function getUser(): ?UserInterface
    {
        return $this->appVariable->getUser();
    }

    public function getSession(): ?Session
    {
        return $this->appVariable->getSession();
    }

    public function getEnvironment(): string
    {
        return $this->appVariable->getEnvironment();
    }

    public function getDebug(): bool
    {
        return $this->appVariable->getDebug();
    }

    /**
     * @param string|list<string>|null $types
     *
     * @return array<mixed>
     */
    public function getFlashes(string|array|null $types = null): array
    {
        return $this->appVariable->getFlashes($types);
    }
}
