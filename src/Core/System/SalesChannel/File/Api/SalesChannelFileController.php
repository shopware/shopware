<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class SalesChannelFileController extends AbstractController
{
    public function __construct(
        private readonly SalesChannelFileAdministrationReader $administrationReader,
        private readonly SalesChannelFileLoader $loader,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly SalesChannelFileRequestPathResolver $requestPathResolver,
    ) {
    }

    #[Route(path: '/api/_action/sales-channel-file/{fileFamily}/{salesChannelId}', name: 'api.action.sales_channel_file.list', methods: ['GET'])]
    public function list(string $fileFamily, string $salesChannelId, Context $context): JsonResponse
    {
        $this->requestPathResolver->validateFileFamily($fileFamily);

        return new JsonResponse(['data' => $this->administrationReader->list($fileFamily, $salesChannelId, $context)]);
    }

    // The public file name supports subfolders like `.well-known/ucp.json`; keeping it
    // as a query parameter avoids a greedy wildcard path segment for an arbitrary file path.
    #[Route(path: '/api/_action/sales-channel-file/{fileFamily}/{salesChannelId}/detail', name: 'api.action.sales_channel_file.detail', methods: ['GET'])]
    public function detail(string $fileFamily, string $salesChannelId, Request $request, Context $context): JsonResponse
    {
        $fileName = $request->query->get('fileName');
        if (!\is_string($fileName)) {
            throw SalesChannelException::missingSalesChannelFileName();
        }

        $this->requestPathResolver->buildTemplatePath($fileFamily, $fileName);

        $file = $this->administrationReader->detail($fileFamily, $fileName, $salesChannelId, $context);
        if ($file === null) {
            throw SalesChannelException::salesChannelFileNotFound($fileFamily, $fileName);
        }

        return new JsonResponse(['data' => $file]);
    }

    #[Route(path: '/api/_action/sales-channel-file/{fileFamily}/{salesChannelId}/preview', name: 'api.action.sales_channel_file.preview', methods: ['POST'])]
    public function preview(string $fileFamily, string $salesChannelId, RequestDataBag $dataBag): JsonResponse
    {
        $fileName = $dataBag->get('fileName');
        if (!\is_string($fileName)) {
            throw SalesChannelException::missingSalesChannelFileName();
        }

        $templatePath = $this->requestPathResolver->buildTemplatePath($fileFamily, $fileName);

        $templateOverrides = $dataBag->get('templateOverrides') ?? [];
        if ($templateOverrides instanceof RequestDataBag) {
            $templateOverrides = $templateOverrides->all();
        }

        if (!\is_array($templateOverrides)) {
            throw SalesChannelException::invalidSalesChannelFileTemplateOverrides();
        }

        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);
        $result = $this->loader->preview($templatePath, $salesChannelContext, $templateOverrides);

        if ($result === null) {
            throw SalesChannelException::salesChannelFileNotFound($fileFamily, $fileName);
        }

        return new JsonResponse([
            'fileName' => $result->fileName,
            'contentType' => $result->contentType,
            'content' => $result->content,
        ]);
    }
}
