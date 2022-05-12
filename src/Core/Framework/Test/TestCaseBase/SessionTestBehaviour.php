<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Use if your test modifies the session
 */
trait SessionTestBehaviour
{
    /**
     * @after
     * @before
     */
    public function clearSession(): void
    {
        /** @var Session $session */
        $session = $this->getContainer()->get('session');
        $session->clear();
        $session->getFlashBag();
    }
}
