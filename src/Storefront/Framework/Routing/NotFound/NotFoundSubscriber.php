<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\NotFound;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Storefront\Framework\Routing\Exception\ErrorRedirectRequestEvent;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('storefront')]
class NotFoundSubscriber implements EventSubscriberInterface, ResetInterface
{
    private const ALL_TAG = 'error-page';
    private const SYSTEM_CONFIG_KEY = 'core.basicInformation.http404Page';

    /**
     * Catch the errors only once in a request cycle, otherwise we get an endless loop
     */
    private bool $handled = false;

    private string $sessionName;

    /**
     * @internal
     *
     * @param AbstractCacheTracer<Response> $cacheTracer
     * @param array{name?: string} $sessionOptions
     */
    public function __construct(
        private readonly HttpKernelInterface $httpKernel,
        private readonly SalesChannelContextServiceInterface $contextService,
        private bool $kernelDebug,
        private readonly CacheInterface $cache,
        private readonly AbstractCacheTracer $cacheTracer,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        array $sessionOptions = []
    ) {
        $this->sessionName = $sessionOptions['name'] ?? 'session-';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onError', -100],
            ],
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function onError(ExceptionEvent $event): void
    {
        if ($this->kernelDebug || $this->handled) {
            return;
        }

        $this->handled = true;

        $request = $event->getRequest();

        $event->stopPropagation();

        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, '');
        $domainId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID, '');
        $languageId = $request->attributes->get(PlatformRequest::HEADER_LANGUAGE_ID, '');

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            // When no sales-channel context is resolved, we need to resolve it now.
            $this->setSalesChannelContext($request);
        }

        // Set missing route scope, so the kernel.response event has it triggered by setResponse.
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE)) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StorefrontRouteScope::ID]);
        }

        $is404StatusCode = $event->getThrowable() instanceof HttpException && $event->getThrowable()->getStatusCode() === Response::HTTP_NOT_FOUND;

        // If the exception is not a 404 status code, we don't need to cache it.
        if (!$is404StatusCode) {
            /** @var Context|null $context */
            $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
            $event->setResponse($this->renderErrorPage($request, $event->getThrowable(), $context ?? Context::createDefaultContext()));

            return;
        }

        /** @var SalesChannelContext $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $name = self::buildName($salesChannelId, $domainId, $languageId);
        $key = $this->generateKey($salesChannelId, $domainId, $languageId, $request, $context);

        $response = $this->cache->get($key, function (ItemInterface $item) use ($event, $name, $context, $request) {
            /** @var Response $response */
            $response = $this->cacheTracer->trace($name, function () use ($event, $request, $context) {
                return $this->renderErrorPage($request, $event->getThrowable(), $context->getContext());
            });

            $item->tag($this->generateTags($name, $event->getRequest(), $context));

            // Remove session cookie from 404 pages, injected by the Symfony session listener
            foreach ($response->headers->getCookies() as $cookie) {
                if ($cookie->getName() === $this->sessionName) {
                    $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
                }
            }

            return $response;
        });

        $event->setResponse($response);
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== self::SYSTEM_CONFIG_KEY) {
            return;
        }

        $this->cacheInvalidator->invalidate([self::ALL_TAG]);
    }

    public function reset(): void
    {
        $this->handled = false;
    }

    private static function buildName(string $salesChannelId, string $domainId, string $languageId): string
    {
        return 'error-page-' . $salesChannelId . $domainId . $languageId;
    }

    private function generateKey(string $salesChannelId, string $domainId, string $languageId, Request $request, SalesChannelContext $context): string
    {
        $key = self::buildName($salesChannelId, $domainId, $languageId) . $this->generator->getSalesChannelContextHash($context);

        $event = new NotFoundPageCacheKeyEvent($key, $request, $context);

        $this->eventDispatcher->dispatch($event);

        return $event->getKey();
    }

    /**
     * @return array<string>
     */
    private function generateTags(string $name, Request $request, SalesChannelContext $context): array
    {
        $tags = array_merge(
            $this->cacheTracer->get($name),
            [$name, self::ALL_TAG]
        );

        $event = new NotFoundPageTagsEvent($tags, $request, $context);

        $this->eventDispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }

    private function setSalesChannelContext(Request $request): void
    {
        $salesChannelId = (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        $context = $this->contextService->get(
            new SalesChannelContextServiceParameters(
                $salesChannelId,
                Uuid::randomHex(),
                $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
                $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID)
            )
        );

        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
    }

    /**
     * We enable HTTP-Cache for this request, so any external reverse proxy can cache also 404 pages.
     * So that kind of requests doesn't hit our PHP application.
     */
    private function renderErrorPage(Request $request, \Throwable $e, Context $context): Response
    {
        $errorRequest = $request->duplicate(null, null, [
            ...$request->attributes->all(),
            '_controller' => '\Shopware\Storefront\Controller\ErrorController::error',
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            PlatformRequest::ATTRIBUTE_CAPTCHA => false,
            'exception' => $e,
        ]);

        $this->eventDispatcher->dispatch(new ErrorRedirectRequestEvent($errorRequest, $e, $context));

        return $this->httpKernel->handle($errorRequest, HttpKernelInterface::MAIN_REQUEST);
    }
}
