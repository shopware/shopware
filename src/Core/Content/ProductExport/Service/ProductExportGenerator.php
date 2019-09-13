<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\Exception\EmptyExportException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\SalesChannelRepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductExportGenerator implements ProductExportGeneratorInterface
{
    /** @var ProductStreamBuilderInterface */
    private $productStreamBuilder;

    /** @var int */
    private $readBufferSize;

    /** @var SalesChannelRepositoryInterface */
    private $productRepository;

    /** @var ProductExportRendererInterface */
    private $productExportRender;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(
        ProductStreamBuilderInterface $productStreamBuilder,
        SalesChannelRepositoryInterface $productRepository,
        ProductExportRendererInterface $productExportRender,
        EventDispatcherInterface $eventDispatcher,
        int $readBufferSize
    ) {
        $this->productStreamBuilder = $productStreamBuilder;
        $this->productRepository = $productRepository;
        $this->productExportRender = $productExportRender;
        $this->eventDispatcher = $eventDispatcher;
        $this->readBufferSize = $readBufferSize;
    }

    public function generate(ProductExportEntity $productExport, ExportBehavior $exportBehavior, SalesChannelContext $context): string
    {
        $filters = $this->productStreamBuilder->buildFilters(
            $productExport->getProductStreamId(),
            $context->getContext()
        );

        $criteria = new Criteria();
        $criteria
            ->addFilter(...$filters)
            ->setLimit($this->readBufferSize)
            ->addAssociation('manufacturer')
            ->addAssociation('media')
            ->addAssociation('categories');

        $iterator = new SalesChannelRepositoryIterator($this->productRepository, $context, $criteria);

        $total = $iterator->getTotal();

        if ($total === 0) {
            throw new EmptyExportException($productExport->getId());
        }

        $content = $this->productExportRender->renderHeader($productExport, $context);

        $productContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderBodyContextEvent(
                [
                    'productExport' => $productExport,
                    'context' => $context,
                ]
            )
        );
        while ($productResult = $iterator->fetch()) {
            /** @var ProductEntity $product */
            foreach ($productResult->getEntities() as $product) {
                $data = $productContext->getContext();
                $data['product'] = $product;

                $content .= $this->productExportRender->renderBody($productExport, $context, $data);
            }

            if ($exportBehavior->preview()) {
                break;
            }
        }
        $content .= $this->productExportRender->renderFooter($productExport, $context);

        return mb_convert_encoding($content, $productExport->getEncoding());
    }
}
