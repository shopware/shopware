<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Command;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Smoke-test entry point for the V2 generation flow.
 *
 * Looks up an order by its human-readable order number, creates an order version, drives the
 * full pipeline (provider -> renderer -> persister) and prints the persisted document plus its
 * media URLs as JSON. Useful for trying the renderer against real order data without going
 * through the admin frontend.
 *
 * Not part of any production flow.
 *
 * @internal
 */
#[AsCommand(
    name: 'document-v2:generate',
    description: 'Generates a V2 document for one order and prints the result as JSON.',
)]
#[Package('after-sales')]
class GenerateDocumentCommand extends Command
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly DocumentGenerator $documentGenerator,
        private readonly EntityRepository $orderRepository,
        private readonly MediaService $mediaService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('orderNumber', InputArgument::REQUIRED, 'Order number (e.g. 10000)')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Document type technical name (e.g. invoice)',
                DocumentType::INVOICE->value,
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'One or more document formats (e.g. html, pdf). Repeat the flag for multiple.',
                [DocumentFormat::HTML->value],
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();

        $orderNumber = (string) $input->getArgument('orderNumber');
        $documentType = (string) $input->getOption('type');
        /** @var list<string> $formats */
        $formats = $input->getOption('format');

        $orderId = $this->resolveOrderId($orderNumber, $context);

        $orderVersionId = $this->orderRepository->createVersion($orderId, $context, 'document-v2-cli');

        $document = $this->documentGenerator->generate(new DocumentGenerationRequest(
            orderId: $orderId,
            orderVersionId: $orderVersionId,
            documentType: $documentType,
            requestedFormats: $formats,
        ), $context);

        $files = [];
        foreach ($document->getDocumentFiles() ?? [] as $file) {
            $media = $file->getMedia();
            $fileName = $media->getFileName() ?? $file->getMediaId();
            $fileExtension = $media->getFileExtension() ?? 'bin';
            $localPath = $this->dumpToLocalPath($file->getMediaId(), $fileName, $fileExtension, $context);

            $files[] = [
                'format' => $file->getDocumentFormat(),
                'mediaId' => $file->getMediaId(),
                'fileName' => $fileName,
                // The persisted media is private; this local copy is the easiest way to open it
                // in a browser via file:// during development.
                'localPath' => $localPath,
                'fileUrl' => 'file://' . $localPath,
                'mediaUrl' => $media->getUrl(),
            ];
        }

        $output->writeln((string) json_encode([
            'documentId' => $document->getId(),
            'documentNumber' => $document->getDocumentNumber(),
            'orderId' => $orderId,
            'orderVersionId' => $orderVersionId,
            'files' => $files,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function dumpToLocalPath(string $mediaId, string $fileName, string $fileExtension, Context $context): string
    {
        $blob = $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scoped): string => $this->mediaService->loadFile($mediaId, $scoped),
        );

        $dir = sys_get_temp_dir() . '/document-v2';
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Temporary directory "%s" is not writable.', $dir)); // @phpstan-ignore shopware.domainException
        }

        $path = \sprintf('%s/%s.%s', $dir, $fileName, $fileExtension);
        file_put_contents($path, $blob);

        return $path;
    }

    private function resolveOrderId(string $orderNumber, Context $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderNumber', $orderNumber))
            ->setLimit(1);

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();

        if (!$order instanceof OrderEntity) {
            throw new \RuntimeException(\sprintf('Order with number "%s" not found.', $orderNumber)); // @phpstan-ignore shopware.domainException
        }

        return $order->getId();
    }
}
