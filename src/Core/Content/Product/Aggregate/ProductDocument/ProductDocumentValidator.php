<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Checks the files of product documents against `shopware.product_documents` before they are written.
 * Only what the write changes is checked: the files that documents get with it.
 *
 * @internal
 */
#[Package('inventory')]
final class ProductDocumentValidator implements EventSubscriberInterface
{
    public const VIOLATION_FILE_EXTENSION_NOT_ALLOWED = 'PRODUCT_DOCUMENT_FILE_EXTENSION_NOT_ALLOWED';

    public const VIOLATION_FILE_TOO_LARGE = 'PRODUCT_DOCUMENT_FILE_TOO_LARGE';

    public const VIOLATION_FILE_SIZE_UNKNOWN = 'PRODUCT_DOCUMENT_FILE_SIZE_UNKNOWN';

    public const VIOLATION_FILE_PRIVATE = 'PRODUCT_DOCUMENT_FILE_PRIVATE';

    public const VIOLATION_FILE_NOT_FOUND = 'PRODUCT_DOCUMENT_FILE_NOT_FOUND';

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly WriteOutcome $writeOutcome,
        private readonly EntityRepository $mediaRepository,
        private readonly ProductDocumentConfig $config,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $translations = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME),
            ['media_id'],
        );

        if ($translations === []) {
            return;
        }

        $violations = new ConstraintViolationList();
        $this->validateFiles($event, $translations, $violations);

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @param list<OutcomeRow> $translations
     */
    private function validateFiles(PreWriteValidationEvent $event, array $translations, ConstraintViolationList $violations): void
    {
        $translationsWithNewFile = [];
        foreach ($translations as $translation) {
            $resultingMediaId = $translation->getResultingValue('media_id');
            if (\is_string($resultingMediaId) && $resultingMediaId !== $translation->getStoredValue('media_id')) {
                $translationsWithNewFile[] = [$translation, Uuid::fromBytesToHex($resultingMediaId)];
            }
        }

        if ($translationsWithNewFile === []) {
            return;
        }

        $files = $this->loadFiles(array_values(array_unique(array_column($translationsWithNewFile, 1))), $event->getContext());
        $allowedExtensions = $this->config->getAllowedExtensions($event->getContext());

        foreach ($translationsWithNewFile as [$translation, $mediaId]) {
            $errorPath = $translation->path . '/mediaId';

            $file = $files->get($mediaId);
            if (!$file instanceof MediaEntity) {
                // unknown, hidden from the caller, or created by the same write
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_NOT_FOUND,
                    messageTemplate: 'The file "{{ mediaId }}" was not found. Upload the file first.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $errorPath,
                ));

                continue;
            }

            if ($file->isPrivate()) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_PRIVATE,
                    messageTemplate: 'The file "{{ mediaId }}" is private, but shoppers have to be able to download the files of product documents.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $errorPath,
                ));

                continue;
            }

            // a linked file lives on another server and can change at any time
            if ($file->hasPath() && MediaUploadService::isExternalUrl($file->getPath())) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_SIZE_UNKNOWN,
                    messageTemplate: 'The file "{{ mediaId }}" is a link to another server. Upload it to the shop first.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $errorPath,
                ));

                continue;
            }

            $size = $file->getFileSize();
            if ($size === null) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_SIZE_UNKNOWN,
                    messageTemplate: 'The size of the file "{{ mediaId }}" is unknown. Upload the file first.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $errorPath,
                ));

                continue;
            }

            $extension = (string) $file->getFileExtension();
            if (!\in_array(mb_strtolower($extension), $allowedExtensions, true)) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_EXTENSION_NOT_ALLOWED,
                    messageTemplate: 'The file type "{{ extension }}" is not allowed for product documents. Allowed are: {{ allowed }}.',
                    parameters: ['{{ extension }}' => $extension, '{{ allowed }}' => implode(', ', $allowedExtensions)],
                    path: $errorPath,
                ));
            }

            if ($size > $this->config->maxFileSize) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_TOO_LARGE,
                    messageTemplate: 'The file is {{ size }}, but the files of product documents can have at most {{ maxSize }}.',
                    parameters: ['{{ size }}' => MemorySizeCalculator::formatToBytes($size), '{{ maxSize }}' => MemorySizeCalculator::formatToBytes($this->config->maxFileSize)],
                    path: $errorPath,
                ));
            }
        }
    }

    /**
     * @param list<string> $mediaIds hex
     */
    private function loadFiles(array $mediaIds, Context $context): MediaCollection
    {
        $criteria = new Criteria($mediaIds);
        $criteria->setTitle('product-document-validator::files');

        return $this->mediaRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param array<string, string> $parameters
     */
    private function violation(string $code, string $messageTemplate, array $parameters, string $path): ConstraintViolation
    {
        return new ConstraintViolation(
            message: strtr($messageTemplate, $parameters),
            messageTemplate: $messageTemplate,
            parameters: $parameters,
            root: null,
            propertyPath: $path,
            invalidValue: null,
            code: $code,
        );
    }
}
