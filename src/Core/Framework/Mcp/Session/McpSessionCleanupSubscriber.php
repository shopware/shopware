<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Session;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Cleans up cached tool results when an MCP session ends (DELETE /api/_mcp).
 * Runs on kernel.terminate so cleanup happens after the response is sent.
 *
 * @internal
 */
#[Package('framework')]
final class McpSessionCleanupSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ToolResultCacheStorage $storage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onKernelTerminate'];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->isMethod('DELETE')) {
            return;
        }

        if (!str_ends_with($request->getPathInfo(), '/_mcp')) {
            return;
        }

        $sessionId = $request->headers->get('Mcp-Session-Id');

        if ($sessionId === null || $sessionId === '') {
            return;
        }

        $this->storage->deleteForSession($sessionId);
    }
}
