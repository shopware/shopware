<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Validator;

use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\Error\XmlValidationError;
use Shopware\Core\Content\ProductExport\ProductExportEntity;

class XmlValidator implements ValidatorInterface
{
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void
    {
        if ($productExportEntity->getFileFormat() !== $productExportEntity::FILE_FORMAT_XML) {
            return;
        }

        $backup_errors = libxml_use_internal_errors(true);

        if (\PHP_VERSION_ID < 80000) {
            $backup = libxml_disable_entity_loader(true);
        } else {
            $backup = false;
        }

        if (!simplexml_load_string($productExportContent)) {
            $errors->add(new XmlValidationError($productExportEntity->getId(), libxml_get_errors()));
        }

        if (\PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($backup);
        }

        libxml_use_internal_errors($backup_errors);
    }
}
