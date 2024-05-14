<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class MaintenanceModeRequestEvent extends Event
{
    /**
     * @param string[] $allowedIps
     *
     * @internal
     */
    public function __construct(
        private readonly Request $request,
        private readonly array $allowedIps,
        private bool $isClientAllowed
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function isClientAllowed(): bool
    {
        return $this->isClientAllowed;
    }

    public function disallowClient(): void
    {
        $this->isClientAllowed = false;
    }

    public function allowClient(): void
    {
        $this->isClientAllowed = true;
    }

    /**
     * @return string[]
     */
    public function getAllowedIps(): array
    {
        return $this->allowedIps;
    }
}
