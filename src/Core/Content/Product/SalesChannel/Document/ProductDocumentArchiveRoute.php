<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Document;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Extension\ProductDocumentArchiveRouteExtension;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductDocumentArchiveRoute
{
    /**
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly ProductDocumentArchiver $archiver,
        private readonly RateLimiter $rateLimiter,
        private readonly SystemConfigService $config,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
        private readonly ExtensionDispatcher $extensions,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/store-api/product/{productId}/documents/archive',
        name: 'store-api.product.documents.archive',
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME],
        methods: [Request::METHOD_GET],
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context): Response
    {
        return $this->extensions->publish(
            name: ProductDocumentArchiveRouteExtension::NAME,
            extension: new ProductDocumentArchiveRouteExtension($productId, $request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $productId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $this->ensureAccepted($productId, $request);

        $criteria = new Criteria([$productId]);
        $criteria->setTitle('product-document-archive-route::product');
        $criteria->addFilter(new ProductAvailableFilter($salesChannelContext->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK));

        if ($this->config->getBool('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelContext->getSalesChannelId())) {
            $criteria->addFilter($this->productCloseoutFilterFactory->create($salesChannelContext));
        }

        $product = $this->productRepository->search($criteria, $salesChannelContext)->getEntities()->first();
        if ($product === null) {
            throw ProductException::productNotFound($productId);
        }

        $archive = $this->archiver->archive($product, $salesChannelContext);
        if ($archive === null) {
            throw ProductException::documentArchiveNotFound($productId);
        }

        $response = new StreamedResponse(function () use ($archive): void {
            $stream = $this->archiver->readStream($archive);
            fpassthru($stream);
            fclose($stream);
        });
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Length', (string) $archive->size);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $archive->fileName,
            (string) preg_replace('/[^\x20-\x7E]|[%"\/\\\\]/u', '_', $archive->fileName),
        ));

        return $response;
    }

    private function ensureAccepted(string $productId, Request $request): void
    {
        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::PRODUCT_DOCUMENTS_ARCHIVE, (string) $request->getClientIp());
        } catch (RateLimitExceededException $exception) {
            $this->logger->notice('A download of product documents was rejected by the rate limit.', [
                'productId' => $productId,
                'waitTime' => $exception->getWaitTime(),
            ]);

            throw ProductException::documentArchiveThrottled($exception->getWaitTime(), $exception);
        }
    }
}
