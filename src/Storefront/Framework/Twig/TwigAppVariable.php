<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * To allow custom server parameters,
 */
#[Package('discovery')]
class TwigAppVariable extends AppVariable
{
    /**
     * The request the cached clone was made from.
     * Long-running workers handle many requests with the same service instance,
     * so the clone is only reused while the inner variable still returns this request.
     */
    private ?Request $sourceRequest = null;

    private ?Request $request = null;

    /**
     * @internal
     *
     * @param array<string> $allowList
     */
    public function __construct(
        private readonly AppVariable $appVariable,
        private readonly array $allowList = []
    ) {
    }

    public function getRequest(): ?Request
    {
        $request = $this->appVariable->getRequest();

        if ($request === null) {
            throw StorefrontFrameworkException::appRequestNotAvailable();
        }

        if ($this->request !== null && $this->sourceRequest === $request) {
            return $this->request;
        }

        $clonedRequest = clone $request;

        $clonedRequest->server = clone $clonedRequest->server;

        foreach ($clonedRequest->server->all() as $key => $_) {
            if (!\in_array(strtolower($key), $this->allowList, true)) {
                $clonedRequest->server->remove($key);
            }
        }

        $this->sourceRequest = $request;
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

    public function getSession(): ?SessionInterface
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
