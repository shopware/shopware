<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Validator\ValidatorInterface;

class ProductExportValidator implements ProductExportValidatorInterface
{
    /** @var ValidatorInterface[] */
    private $validators;

    public function __construct(iterable $validators)
    {
        $this->validators = $validators;
    }

    public function validate(ProductExportEntity $productExportEntity, string $productExportContent): array
    {
        $errors = new ErrorCollection();
        foreach ($this->validators as $validator) {
            $validator->validate($productExportEntity, $productExportContent, $errors);
        }

        return array_values($errors->getElements());
    }
}
