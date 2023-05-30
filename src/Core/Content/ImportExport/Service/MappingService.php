<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Exception\FileEmptyException;
use Shopware\Core\Content\ImportExport\Exception\FileNotReadableException;
use Shopware\Core\Content\ImportExport\Exception\InvalidFileContentException;
use Shopware\Core\Content\ImportExport\Exception\UnexpectedFileTypeException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('system-settings')]
class MappingService extends AbstractMappingService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractFileService $fileService,
        private readonly EntityRepository $profileRepository,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
    }

    public function getDecorated(): AbstractMappingService
    {
        throw new DecorationPatternException(self::class);
    }

    public function createTemplate(Context $context, string $profileId): string
    {
        /** @var ImportExportProfileEntity|null $profile */
        $profile = $this->profileRepository->search(new Criteria([$profileId]), $context)->first();
        if ($profile === null) {
            throw new EntityNotFoundException('import_export_profile', $profileId);
        }
        $mappings = $profile->getMapping();
        if (empty($mappings)) {
            throw new \RuntimeException('ImportExportProfile "' . $profileId . '" has no mappings');
        }

        $config = new Config($mappings, [], []);
        $headers = [];
        $mappings = MappingCollection::fromIterable($mappings)->sortByPosition();

        /** @var Mapping $mapping */
        foreach ($mappings as $mapping) {
            $headers[$mapping->getMappedKey()] = '';
        }

        // create the file
        $expireDate = new \DateTimeImmutable();
        $expireDate = $expireDate->modify('+1 hour');
        $fileEntity = $this->fileService->storeFile(
            $context,
            $expireDate,
            null,
            $profile->getSourceEntity() . ':' . $profile->getName() . '.csv',
            ImportExportLogEntity::ACTIVITY_TEMPLATE
        );

        // write to the file
        $targetFile = $fileEntity->getPath();
        $writer = $this->fileService->getWriter();
        $writer->append($config, $headers, 0);
        $writer->flush($config, $targetFile);
        $writer->finish($config, $targetFile);

        return $fileEntity->getId();
    }

    public function getMappingFromTemplate(
        Context $context,
        UploadedFile $file,
        string $sourceEntity,
        string $delimiter = ';',
        string $enclosure = '"',
        string $escape = '\\'
    ): MappingCollection {
        if ($this->fileService->detectType($file) !== 'text/csv') {
            throw new UnexpectedFileTypeException($file->getClientMimeType(), 'text/csv');
        }

        if ($file->getSize() < 1) {
            throw new FileEmptyException($file->getFilename());
        }

        $filePath = $file->getRealPath();
        if (!$filePath) {
            throw new \RuntimeException('File does not exists');
        }

        $fileHandle = fopen($filePath, 'rb');
        if (!$fileHandle) {
            throw new FileNotReadableException($filePath);
        }

        // read the first CSV line
        $record = fgetcsv($fileHandle, 0, $delimiter, $enclosure, $escape);
        fclose($fileHandle);
        if (empty($record) || $record[0] === null) {
            throw new InvalidFileContentException($file->getFilename());
        }

        // construct the mapping from the given CSV line data
        $definition = $this->definitionInstanceRegistry->getByEntityName($sourceEntity);
        $keyLookupTable = $this->getKeyLookupTable($context, $sourceEntity);

        $mappings = new MappingCollection();
        foreach ($record as $index => $column) {
            $mappings->add(new Mapping(
                $this->guessKeyFromMappedKey($keyLookupTable, $column, $definition),
                $column,
                $index
            ));
        }

        return $mappings;
    }

    /**
     * Gather all mapping keys used in all profiles with the same source entity and fill the keyLookupTable.
     * Keys from newer profiles are prioritized.
     */
    private function getKeyLookupTable(Context $context, string $sourceEntity): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('sourceEntity', $sourceEntity));
        $criteria->addSorting(new FieldSorting('createdAt'));
        $profiles = $this->profileRepository->search($criteria, $context)->getEntities();

        $keyLookupTable = [];
        foreach ($profiles as $profile) {
            $mappings = $profile->getMapping();
            if ($mappings !== null) {
                foreach ($mappings as $mapping) {
                    if (!empty($mapping['key']) && !empty($mapping['mappedKey'])) {
                        $keyLookupTable[$mapping['mappedKey']] = $mapping['key'];
                    }
                }
            }
        }

        return $keyLookupTable;
    }

    /**
     * Guess the mapping keys based on the following things (in order):
     * 1. Use the keyLookupTable: check if the mappedKey was already used in another profile with the same sourceEntity
     * 2. check if the mappedKey (converted to camelCase) is a field of the translationDefinition (if one exists)
     * 3. check if the mappedKey (converted to camelCase) is a field of the definition itself
     * 4. split the mappedKey in parts and check if the first part is an association -> recursive call this method again.
     */
    private function guessKeyFromMappedKey(array $keyLookupTable, string $mappedKey, EntityDefinition $definition, int $depthLimit = 5): string
    {
        if ($depthLimit < 1) {
            return '';
        }

        if (!empty($keyLookupTable[$mappedKey])) {
            return $keyLookupTable[$mappedKey];
        }

        $camelCaseMappedKey = $this->convertToCamelCase($mappedKey);

        // check direct match of translated field
        $translationDefinition = $definition->getTranslationDefinition();
        if ($translationDefinition !== null) {
            $translatedFieldExactMatch = $translationDefinition->getField($camelCaseMappedKey);
            if (
                $translatedFieldExactMatch !== null
                && !($translatedFieldExactMatch instanceof FkField)
                && !($translatedFieldExactMatch instanceof IdField)
            ) {
                return 'translations.DEFAULT.' . $translatedFieldExactMatch->getPropertyName();
            }
        }

        // check direct match of field on entity
        $fieldExactMatch = $definition->getField($camelCaseMappedKey);
        if ($fieldExactMatch !== null) {
            if (!($fieldExactMatch instanceof FkField && $fieldExactMatch->getReferenceDefinition() !== $definition)) {
                // prefer ids in the association ('tax.id' over 'taxId'), so skip normal FkFields at this point
                // but still return self reference fields (like 'parentId' over 'parent.id')
                // every other field is fine, and it's property name can be used here.
                return $fieldExactMatch->getPropertyName();
            }
        }

        // try to guess associations
        /** @var array<string> $mappedKeyParts */
        $mappedKeyParts = explode(
            ' ',
            strtolower(
                str_replace(
                    '-',
                    ' ',
                    str_replace(
                        '_',
                        ' ',
                        $mappedKey
                    )
                )
            )
        );

        if (isset($mappedKeyParts[0]) && strcmp($mappedKeyParts[0], $mappedKey) !== 0) {
            $associationField = $definition->getField($mappedKeyParts[0]);

            if ($associationField !== null && $associationField instanceof ManyToOneAssociationField) {
                $fullKey = implode(' ', $mappedKeyParts);
                array_shift($mappedKeyParts);
                $leftoverKey = implode(' ', $mappedKeyParts);

                $associationDefinition = $associationField->getReferenceDefinition();

                // try full key first (something like 'tax_rate' which is a field of the tax entity).
                $associationGuess = $this->guessKeyFromMappedKey($keyLookupTable, $fullKey, $associationDefinition, $depthLimit - 1);
                if (!empty($associationGuess)) {
                    return $associationField->getPropertyName() . '.' . $associationGuess;
                }

                // try the leftover key next (something like 'rate' if the full key was 'tax_rate').
                $associationGuess = $this->guessKeyFromMappedKey($keyLookupTable, $leftoverKey, $associationDefinition, $depthLimit - 1);
                if (!empty($associationGuess)) {
                    return $associationField->getPropertyName() . '.' . $associationGuess;
                }
            }
        }

        // not mapped key value
        return '';
    }

    /**
     * Try to convert any snake_case or dash-case string to camelCase.
     * The default naming scheme of import/export mappings is snake_case,
     * so it may give the right property name (which is camelCase).
     */
    private function convertToCamelCase(string $input): string
    {
        $str = str_replace(
            ' ',
            '',
            ucwords(
                str_replace(
                    ['-', '_'],
                    ' ',
                    $input
                )
            )
        );

        return lcfirst($str);
    }
}
