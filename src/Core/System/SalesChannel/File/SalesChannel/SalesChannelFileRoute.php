<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\SalesChannel;

use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Extension\SalesChannelFileRouteExtension;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SalesChannelFileRoute
{
    public function __construct(
        private readonly SalesChannelFileLoader $loader,
        private readonly SalesChannelFileRequestPathResolver $requestPathResolver,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/sales-channel-file/{fileName}',
        name: 'store-api.sales-channel-file',
        requirements: ['fileName' => '.+'],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
    )]
    public function load(string $fileName, SalesChannelContext $context): Response
    {
        return $this->extensions->publish(
            name: SalesChannelFileRouteExtension::NAME,
            extension: new SalesChannelFileRouteExtension($fileName, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $fileName, SalesChannelContext $context): Response
    {
        $templatePath = $this->requestPathResolver->buildTemplatePath(SalesChannelFile::DEFAULT_FILE_FAMILY, $fileName);

        $file = $this->loader->load($templatePath, $context);
        if ($file === null) {
            throw SalesChannelException::salesChannelFileNotFound(SalesChannelFile::DEFAULT_FILE_FAMILY, $fileName);
        }

        return new Response($file->content, Response::HTTP_OK, ['content-type' => $file->contentType]);
    }
}
