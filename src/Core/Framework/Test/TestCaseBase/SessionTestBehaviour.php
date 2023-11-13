<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Use if your test modifies the session
 */
trait SessionTestBehaviour
{
    /**
     * @after
     */
    public function clearSession(): void
    {
        $session = $this->getSession();
        $session->clear();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->clear();
        }
    }

    public function getSession(): SessionInterface
    {
        /** @var SessionFactoryInterface $factory */
        $factory = $this->getContainer()->get('session.factory');

        return $factory->createSession();
    }
}
