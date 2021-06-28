<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

trait RequestStackTestBehaviour
{
    /**
     * @before
     * @after
     */
    public function clearRequestStack(): array
    {
        $stack = $this->getContainer()
            ->get(RequestStack::class);

        $requests = [];

        while ($stack->getMainRequest()) {
            $requests[] = $stack->pop();
        }

        return $requests;
    }

    abstract protected function getContainer(): ContainerInterface;
}
