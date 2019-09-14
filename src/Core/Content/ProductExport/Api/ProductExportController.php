<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Api;

use Shopware\Core\Content\ProductExport\Exception\RenderFooterException;
use Shopware\Core\Content\ProductExport\Exception\RenderHeaderException;
use Shopware\Core\Content\ProductExport\Exception\RenderProductException;
use Shopware\Core\Content\ProductExport\Exception\SalesChannelDomainNotFoundException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExportGeneratorInterface;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Translation\Translator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ProductExportController extends AbstractController
{
    /** @var SalesChannelContextFactory */
    private $salesChannelContextFactory;

    /** @var EntityRepositoryInterface */
    private $salesChannelDomainRepository;

    /** @var ProductExportGeneratorInterface */
    private $productExportGenerator;

    /** @var Translator */
    private $translator;

    public function __construct(
        SalesChannelContextFactory $salesChannelContextFactory,
        EntityRepositoryInterface $salesChannelDomainRepository,
        ProductExportGeneratorInterface $productExportGenerator,
        Translator $translator
    ) {
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
        $this->productExportGenerator = $productExportGenerator;
        $this->translator = $translator;
    }

    /**
     * @Route("/api/v{version}/_action/product-export/validate", name="api.action.product_export.validate",
     *                                                           methods={"POST"})
     *
     * @throws RenderHeaderException
     * @throws RenderProductException
     * @throws RenderFooterException
     */
    public function validate(RequestDataBag $dataBag, Context $context): JsonResponse
    {
        $this->generateExportPreview($dataBag, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/product-export/preview", name="api.action.product_export.preview",
     *                                                           methods={"POST"})
     *
     * @throws RenderHeaderException
     * @throws RenderProductException
     * @throws RenderFooterException
     */
    public function preview(RequestDataBag $dataBag, Context $context): JsonResponse
    {
        return new JsonResponse(
            [
                'content' => mb_convert_encoding(
                    $this->generateExportPreview($dataBag, $context),
                    'UTF-8',
                    $dataBag->get('encoding')
                ),
            ]
        );
    }

    private function createEntity(RequestDataBag $dataBag): ProductExportEntity
    {
        $entity = new ProductExportEntity();

        $entity->setId('');
        $entity->setHeaderTemplate($dataBag->get('header_template'));
        $entity->setBodyTemplate($dataBag->get('body_template'));
        $entity->setFooterTemplate($dataBag->get('footer_template'));
        $entity->setProductStreamId($dataBag->get('product_stream_id'));
        $entity->setIncludeVariants($dataBag->get('include_variants'));
        $entity->setEncoding($dataBag->get('encoding'));
        $entity->setSalesChannelId($dataBag->get('sales_channel_id'));
        $entity->setSalesChannelDomainId($dataBag->get('sales_channel_domain_id'));

        return $entity;
    }

    private function generateExportPreview(RequestDataBag $dataBag, Context $context): string
    {
        $salesChannelDomainId = $dataBag->get('sales_channel_domain_id');

        $salesChannelDomain = $this->salesChannelDomainRepository->search(
            (new Criteria([$salesChannelDomainId]))->addAssociation('language'),
            $context
        )->get($salesChannelDomainId);

        if (!($salesChannelDomain instanceof SalesChannelDomainEntity)) {
            throw new SalesChannelDomainNotFoundException($salesChannelDomainId);
        }

        $salesChannelContext = $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelDomain->getSalesChannelId()
        );
        $productExportEntity = $this->createEntity($dataBag);
        $exportBehavior = new ExportBehavior(true, true, true);

        $this->translator->injectSettings(
            $salesChannelDomain->getSalesChannelId(),
            $salesChannelDomain->getLanguageId(),
            $salesChannelDomain->getLanguage()->getLocaleId(),
            $context
        );

        return $this->productExportGenerator->generate($productExportEntity, $exportBehavior, $salesChannelContext);
    }
}
