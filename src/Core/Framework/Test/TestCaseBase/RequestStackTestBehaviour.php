<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

trait RequestStackTestBehaviour
{
    /**
     * @before
     *
     * @after
     *
     * @return array<Request>
     */
    public function clearRequestStack(): array
    {
        $stack = $this->getContainer()
            ->get(RequestStack::class);

        $requests = [];

        while ($stack->getMainRequest()) {
            if ($request = $stack->pop()) {
                $requests[] = $request;
            }
        }

        return $requests;
    }

    abstract protected function getContainer(): ContainerInterface;
}
