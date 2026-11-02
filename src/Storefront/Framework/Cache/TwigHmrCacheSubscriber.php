<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The Twig hot reload of the Storefront watcher renders the page again after a template changed.
 * A cached response would still show the old template, so its requests bypass the HTTP cache in debug mode.
 *
 * Twig compiles the parent of `sw_extends` into the template and only checks the modification time of the template itself.
 * After a template was added or removed, the watcher therefore asks to drop the compiled templates once.
 *
 * @internal
 */
#[Package('framework')]
class TwigHmrCacheSubscriber implements EventSubscriberInterface
{
    final public const HEADER = 'sw-twig-hmr';

    final public const RESET_HEADER = 'sw-twig-hmr-reset';

    public function __construct(
        private readonly bool $debug,
        private readonly string $twigCacheDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            HttpCacheKeyEvent::class => 'bypassCache',
            // before the HTTP cache and anything else renders a template
            KernelEvents::REQUEST => ['resetTwigCache', 4096],
        ];
    }

    public function bypassCache(HttpCacheKeyEvent $event): void
    {
        if (!$this->isHotReloadRequest($event->request, self::HEADER)) {
            return;
        }

        $event->isCacheable = false;
    }

    public function resetTwigCache(RequestEvent $event): void
    {
        if (!$event->isMainRequest()
            || !$this->isHotReloadRequest($event->getRequest(), self::RESET_HEADER)
            || !$this->filesystem->exists($this->twigCacheDir)
        ) {
            return;
        }

        // Only the compiled templates are removed. A parallel render writes a temporary file into a directory and renames it then,
        // so removing the directories or the temporary files makes that render fail with "Failed to write cache file".
        // A render that misses a just removed template compiles it again.
        $this->filesystem->remove(
            (new Finder())->files()->in($this->twigCacheDir)->name('*.php')
        );
    }

    private function isHotReloadRequest(Request $request, string $header): bool
    {
        return $this->debug && $request->headers->get($header) === '1';
    }
}
