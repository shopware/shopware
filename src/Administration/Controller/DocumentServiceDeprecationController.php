<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Checkout\Document\Controller\DocumentController;
use Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorInterface;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorRegistry;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator;
use Shopware\Core\Checkout\Document\DocumentGeneratorController;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Event\DocumentOrderCriteriaEvent;
use Shopware\Core\Checkout\Document\FileGenerator\FileGeneratorInterface;
use Shopware\Core\Checkout\Document\FileGenerator\PdfGenerator;
use Shopware\Core\Framework\Feature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal Only to be used by the bulk-edit-feature.
 *
 * @deprecated tag:v6.5.0 - Will be removed
 * @Route(defaults={"_routeScope"={"administration"}})
 */
class DocumentServiceDeprecationController
{
    private DocumentService $documentService;

    private DocumentGeneratorRegistry $documentGeneratorRegistry;

    private FileGeneratorInterface $pdfGenerator;

    private DocumentGeneratorInterface $invoiceGenerator;

    private DocumentGeneratorInterface $deliveryNoteGenerator;

    private DocumentGeneratorInterface $stornoGenerator;

    private DocumentGeneratorInterface $creditNoteGenerator;

    private AbstractController $documentGeneratorController;

    private AbstractController $documentController;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     */
    public function __construct(
        DocumentService $documentService,
        DocumentGeneratorRegistry $documentGeneratorRegistry,
        FileGeneratorInterface $pdfGenerator,
        DocumentGeneratorInterface $invoiceGenerator,
        DocumentGeneratorInterface $deliveryNoteGenerator,
        DocumentGeneratorInterface $stornoGenerator,
        DocumentGeneratorInterface $creditNoteGenerator,
        AbstractController $documentGeneratorController,
        AbstractController $documentController,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->documentService = $documentService;
        $this->documentGeneratorRegistry = $documentGeneratorRegistry;
        $this->pdfGenerator = $pdfGenerator;
        $this->invoiceGenerator = $invoiceGenerator;
        $this->deliveryNoteGenerator = $deliveryNoteGenerator;
        $this->stornoGenerator = $stornoGenerator;
        $this->creditNoteGenerator = $creditNoteGenerator;
        $this->documentGeneratorController = $documentGeneratorController;
        $this->documentController = $documentController;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Route("/api/_action/document/extending-deprecated-service", name="api.action.document.extending-deprecated-service", methods={"GET"})
     */
    public function check(): Response
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $deprecatedServices = [
            \get_class($this->documentService) => DocumentService::class,
            \get_class($this->documentGeneratorRegistry) => DocumentGeneratorRegistry::class,
            \get_class($this->pdfGenerator) => PdfGenerator::class,
            \get_class($this->invoiceGenerator) => InvoiceGenerator::class,
            \get_class($this->deliveryNoteGenerator) => DeliveryNoteGenerator::class,
            \get_class($this->stornoGenerator) => StornoGenerator::class,
            \get_class($this->creditNoteGenerator) => CreditNoteGenerator::class,
            \get_class($this->documentGeneratorController) => DocumentGeneratorController::class,
            \get_class($this->documentController) => DocumentController::class,
        ];

        foreach ($deprecatedServices as $serviceClass => $originalClass) {
            if ($serviceClass !== $originalClass) {
                return new JsonResponse([
                    'showWarning' => true,
                ]);
            }
        }

        $listeners = $this->listenersOfDeprecatedEvent();

        if (!empty($listeners)) {
            return new JsonResponse([
                'showWarning' => true,
            ]);
        }

        return new JsonResponse([
            'showWarning' => false,
        ]);
    }

    private function listenersOfDeprecatedEvent(): array
    {
        return $this->eventDispatcher->getListeners(DocumentOrderCriteriaEvent::class) ?: [];
    }
}
