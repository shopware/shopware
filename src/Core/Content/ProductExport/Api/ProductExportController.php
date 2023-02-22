<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Api;

use Monolog\Level;
use Shopware\Core\Content\ProductExport\Error\Error;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Exception\SalesChannelDomainNotFoundException;
use Shopware\Core\Content\ProductExport\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExportGeneratorInterface;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('sales-channel')]
class ProductExportController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly ProductExportGeneratorInterface $productExportGenerator,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route(path: '/api/_action/product-export/validate', name: 'api.action.product_export.validate', methods: ['POST'])]
    public function validate(RequestDataBag $dataBag, Context $context): JsonResponse
    {
        $result = $this->generateExportPreview($dataBag, $context);

        if ($result && $result->hasErrors()) {
            $errors = $result->getErrors();
            $errorMessages = array_merge(
                ...array_map(
                    fn (Error $error) => $error->getErrorMessages(),
                    $errors
                )
            );

            return new JsonResponse(
                [
                    'content' => mb_convert_encoding(
                        $result->getContent(),
                        'UTF-8',
                        $dataBag->get('encoding')
                    ),
                    'errors' => $errorMessages,
                ]
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/product-export/preview', name: 'api.action.product_export.preview', methods: ['POST'])]
    public function preview(RequestDataBag $dataBag, Context $context): JsonResponse
    {
        $result = $this->generateExportPreview($dataBag, $context);

        if ($result && $result->hasErrors()) {
            $errors = $result->getErrors();
            $errorMessages = array_merge(
                ...array_map(
                    fn (Error $error) => $error->getErrorMessages(),
                    $errors
                )
            );
        }

        return new JsonResponse(
            [
                'content' => mb_convert_encoding(
                    $result ? $result->getContent() : '',
                    'UTF-8',
                    $dataBag->get('encoding')
                ),
                'errors' => $errorMessages ?? [],
            ]
        );
    }

    private function createEntity(RequestDataBag $dataBag): ProductExportEntity
    {
        $entity = new ProductExportEntity();

        $entity->setId('');
        $entity->setHeaderTemplate($dataBag->get('headerTemplate') ?? '');
        $entity->setBodyTemplate($dataBag->get('bodyTemplate') ?? '');
        $entity->setFooterTemplate($dataBag->get('footerTemplate') ?? '');
        $entity->setProductStreamId($dataBag->get('productStreamId'));
        $entity->setIncludeVariants($dataBag->get('includeVariants'));
        $entity->setEncoding($dataBag->get('encoding'));
        $entity->setFileFormat($dataBag->get('fileFormat'));
        $entity->setFileName($dataBag->get('fileName'));
        $entity->setAccessKey($dataBag->get('accessKey'));
        $entity->setSalesChannelId($dataBag->get('salesChannelId'));
        $entity->setSalesChannelDomainId($dataBag->get('salesChannelDomainId'));
        $entity->setCurrencyId($dataBag->get('currencyId'));

        return $entity;
    }

    private function generateExportPreview(RequestDataBag $dataBag, Context $context): ?ProductExportResult
    {
        $salesChannelDomain = $this->getSalesChannelDomain($dataBag->get('salesChannelDomainId'), $context);
        $salesChannel = $this->getSalesChannel($dataBag->get('salesChannelId'), $context);

        $productExportEntity = $this->createEntity($dataBag);
        $productExportEntity->setSalesChannelDomain($salesChannelDomain);
        $productExportEntity->setStorefrontSalesChannelId($salesChannelDomain->getSalesChannelId());
        $productExportEntity->setSalesChannel($salesChannel);

        $exportBehavior = new ExportBehavior(true, true, true);

        return $this->productExportGenerator->generate($productExportEntity, $exportBehavior);
    }

    private function getSalesChannelDomain(string $salesChannelDomainId, Context $context): SalesChannelDomainEntity
    {
        $criteria = (new Criteria([$salesChannelDomainId]))
            ->addAssociation('language.locale')
            ->addAssociation('salesChannel');
        $salesChannelDomain = $this->salesChannelDomainRepository->search(
            $criteria,
            $context
        )->get($salesChannelDomainId);

        if (!($salesChannelDomain instanceof SalesChannelDomainEntity)) {
            $salesChannelDomainNotFoundException = new SalesChannelDomainNotFoundException($salesChannelDomainId);
            $loggingEvent = new ProductExportLoggingEvent(
                $context,
                $salesChannelDomainNotFoundException->getMessage(),
                Level::Error,
                $salesChannelDomainNotFoundException
            );
            $this->eventDispatcher->dispatch($loggingEvent);

            throw $salesChannelDomainNotFoundException;
        }

        return $salesChannelDomain;
    }

    private function getSalesChannel(string $salesChannelId, Context $context): SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);

        $salesChannel = $this->salesChannelRepository->search(
            $criteria,
            $context
        )->get($salesChannelId);

        if (!($salesChannel instanceof SalesChannelEntity)) {
            $salesChannelNotFoundException = new SalesChannelNotFoundException($salesChannelId);
            $loggingEvent = new ProductExportLoggingEvent(
                $context,
                $salesChannelNotFoundException->getMessage(),
                Level::Error,
                $salesChannelNotFoundException
            );
            $this->eventDispatcher->dispatch($loggingEvent);

            throw $salesChannelNotFoundException;
        }

        return $salesChannel;
    }
}
