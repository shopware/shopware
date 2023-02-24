<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
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

    /**
     * @after
     */
    public function resetRequestContext(): void
    {
        $router = $this->getContainer()
            ->get('router');

        $context = $router->getContext();

        $router->setContext($context->fromRequest(Request::create((string) EnvironmentHelper::getVariable('APP_URL'))));
    }

    abstract protected static function getContainer(): ContainerInterface;
}
