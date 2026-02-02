<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Session;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;

/**
 * Same implementation as @see \Symfony\Component\HttpFoundation\Session\SessionFactory
 * But it uses our FlashBag wrapper which triggers whether flash messages were cleared or not
 *
 * @internal
 */
#[Package('framework')]
class SessionFactory implements SessionFactoryInterface
{
    private ?\Closure $usageReporter;

    private ?StatefulFlashBag $flashBag = null;

    public function __construct(
        private RequestStack $requestStack,
        private SessionStorageFactoryInterface $storageFactory,
        ?callable $usageReporter = null,
    ) {
        $this->usageReporter = $usageReporter === null ? null : $usageReporter(...);
    }

    public function createSession(): SessionInterface
    {
        $this->flashBag = new StatefulFlashBag();

        return new Session(
            $this->storageFactory->createStorage($this->requestStack->getMainRequest()),
            null,
            $this->flashBag,
            $this->usageReporter
        );
    }

    public function getFlashBag(): ?StatefulFlashBag
    {
        return $this->flashBag;
    }
}
