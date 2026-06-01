<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serves configured sales channel files after normal routing missed.
 *
 * Explicit routes keep precedence because this subscriber only handles unresolved 404s, and only when
 * the request already carries a sales channel context from the active request scope.
 *
 * @internal
 */
#[Package('framework')]
class SalesChannelFileNotFoundSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SalesChannelFileLoader $loader,
        private readonly SalesChannelFileRequestPathResolver $requestPathResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onNotFound', -90],
        ];
    }

    public function onNotFound(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() !== Response::HTTP_NOT_FOUND) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            return;
        }

        if (!\in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_HEAD], true)) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext) {
            return;
        }

        try {
            $templatePath = $this->requestPathResolver->buildTemplatePath(
                SalesChannelFile::DEFAULT_FILE_FAMILY,
                ltrim($request->getPathInfo(), '/'),
            );
        } catch (SalesChannelException) {
            return;
        }

        $file = $this->loader->load($templatePath, $context);
        if ($file === null) {
            return;
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);

        $event->setResponse(new Response(
            $file->content,
            Response::HTTP_OK,
            ['content-type' => $file->contentType],
        ));
    }
}
