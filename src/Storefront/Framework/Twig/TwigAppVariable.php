<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @package core
 *
 * To allow custom server parameters,
 */
class TwigAppVariable extends AppVariable implements ResetInterface
{
    private ?Request $request = null;

    private AppVariable $appVariable;

    /**
     * @var list<string>
     */
    private array $allowList;

    private array $flashes = [];

    /**
     * @internal
     *
     * @param list<string> $allowList
     */
    public function __construct(AppVariable $appVariable, array $allowList = [])
    {
        $this->allowList = $allowList;
        $this->appVariable = $appVariable;
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
        if (\is_string($types)) {
            $flashes = $this->appVariable->getFlashes($types);
            $result = $flashes;

            if (isset($this->flashes[$types])) {
                $result = [...$result, ...$this->flashes[$types]];
            }

            $this->flashes[$types] = [...$flashes, ...($this->flashes[$types] ?? [])];
        } else {
            $flashes = $this->appVariable->getFlashes($types);
            $result = $flashes;

            foreach ($this->flashes as $key => $values) {
                if ($types === null || \in_array($key, $types)) {
                    $result[$key] = [...$values, ...$result[$key] ?? []];
                }
            }

            foreach ($flashes as $key => $values) {
                $this->flashes[$key] = [...$values, ...($this->flashes[$key] ?? [])];
            }
        }

        return $result;
    }

    public function reset(): void
    {
        $this->request = null;
        $this->flashes = [];
    }
}
