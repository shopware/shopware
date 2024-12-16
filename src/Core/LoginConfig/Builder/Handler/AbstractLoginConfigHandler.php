<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\Builder\Handler;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\LoginConfig\Builder\LoginConfigItem;
use Shopware\Core\LoginConfig\LoginConfigException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
#[Package('core')]
abstract class AbstractLoginConfigHandler
{
    protected ?SessionInterface $session;

    public function supports(string $type): bool
    {
        return $type === $this->getType();
    }

    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function createTemplateData(LoginConfigItem $loginConfigItem): array;

    protected function getSession(): SessionInterface
    {
        if (!$this->session instanceof SessionInterface) {
            throw LoginConfigException::sessionIsNotSet();
        }

        return $this->session;
    }

    abstract protected function getType(): string;
}
