<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Validator;

use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\Error\ProviderValidationError;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UrlEncoder;

/**
 * Validates Google Merchant Center XML feeds (RSS 2.0 with the http://base.google.com/ns/1.0 namespace).
 *
 * @deprecated tag:v6.8.0 - Will be removed and is going to be part of SwagAgenticCommerce
 */
#[Package('discovery')]
class GoogleProductExportValidator extends AbstractProviderValidator
{
    private const GOOGLE_NAMESPACE = 'http://base.google.com/ns/1.0';

    /**
     * @var list<string>
     */
    private const ALLOWED_AVAILABILITY_VALUES = [
        'in_stock',
        'out_of_stock',
        'preorder',
        'backorder',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_CONDITION_VALUES = [
        'new',
        'refurbished',
        'used',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_GENDER_VALUES = [
        'male',
        'female',
        'unisex',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_SIZE_SYSTEM_VALUES = [
        'AU',
        'BR',
        'CN',
        'DE',
        'EU',
        'FR',
        'IT',
        'JP',
        'MEX',
        'UK',
        'US',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_AGE_GROUP_VALUES = [
        'newborn',
        'infant',
        'toddler',
        'kids',
        'adult',
    ];

    /**
     * @var list<string>
     */
    private const REQUIRED_GOOGLE_FIELDS = [
        'id',
        'availability',
        'condition',
        'price',
        'image_link',
        'brand',
    ];

    protected function getProviderTechnicalName(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'Will be part of SwagAgenticCommerce'));

        return 'google';
    }

    protected function validateProviderExport(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'Will be part of SwagAgenticCommerce'));

        if ($productExportEntity->getFileFormat() !== ProductExportEntity::FILE_FORMAT_XML) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                'file_format',
                'Google product exports must use the "xml" file format.'
            ));

            return;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($productExportContent);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                'xml',
                'The Google feed must be valid XML.'
            ));

            return;
        }

        $items = $xml->xpath('//item');

        if (!\is_array($items) || $items === []) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                'item',
                'The Google feed must contain at least one <item> element.'
            ));

            return;
        }

        $itemIds = [];

        foreach ($items as $index => $item) {
            $line = $index + 1;
            $googleChildren = $item->children(self::GOOGLE_NAMESPACE);

            foreach (self::REQUIRED_GOOGLE_FIELDS as $field) {
                $value = (string) ($googleChildren->{$field} ?? '');

                if (trim($value) === '') {
                    $errors->add(new ProviderValidationError(
                        $productExportEntity->getId(),
                        $this->getProviderTechnicalName(),
                        $field,
                        \sprintf('The required field "g:%s" is missing or empty.', $field),
                        $line
                    ));
                }
            }

            $title = trim((string) ($item->title ?? ''));
            if ($title === '') {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'title',
                    'The required field "title" is missing or empty.',
                    $line
                ));
            }

            $link = trim((string) ($item->link ?? ''));
            if ($link === '' || filter_var(UrlEncoder::encodeUrl($link), \FILTER_VALIDATE_URL) === false) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'link',
                    'The field "link" must be a valid absolute URL.',
                    $line
                ));
            }

            $imageLink = trim((string) ($googleChildren->image_link ?? ''));
            if ($imageLink !== '' && filter_var(UrlEncoder::encodeUrl($imageLink), \FILTER_VALIDATE_URL) === false) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'image_link',
                    'The field "g:image_link" must be a valid absolute URL.',
                    $line
                ));
            }

            $availability = trim((string) ($googleChildren->availability ?? ''));
            if ($availability !== '' && !\in_array($availability, self::ALLOWED_AVAILABILITY_VALUES, true)) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'availability',
                    'The field "g:availability" must be one of: in_stock, out_of_stock, preorder, backorder.',
                    $line
                ));
            }

            $condition = trim((string) ($googleChildren->condition ?? ''));
            if ($condition !== '' && !\in_array($condition, self::ALLOWED_CONDITION_VALUES, true)) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'condition',
                    'The field "g:condition" must be one of: new, refurbished, used.',
                    $line
                ));
            }

            $price = trim((string) ($googleChildren->price ?? ''));
            if ($price !== '' && preg_match('/^\d+(?:\.\d+)? [A-Z]{3}$/', $price) !== 1) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'price',
                    'The field "g:price" must be formatted as "<number> <ISO-4217>".',
                    $line
                ));
            }

            $salePrice = trim((string) ($googleChildren->sale_price ?? ''));
            if ($salePrice !== '' && preg_match('/^\d+(?:\.\d+)? [A-Z]{3}$/', $salePrice) !== 1) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'sale_price',
                    'The field "g:sale_price" must be formatted as "<number> <ISO-4217>".',
                    $line
                ));
            }

            $id = trim((string) ($googleChildren->id ?? ''));
            if ($id !== '') {
                if (isset($itemIds[$id])) {
                    $errors->add(new ProviderValidationError(
                        $productExportEntity->getId(),
                        $this->getProviderTechnicalName(),
                        'id',
                        \sprintf('The g:id "%s" is not unique in the feed.', $id),
                        $line
                    ));
                }

                $itemIds[$id] = true;
            }

            $gender = trim((string) ($googleChildren->gender ?? ''));
            if ($gender !== '' && !\in_array($gender, self::ALLOWED_GENDER_VALUES, true)) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'gender',
                    'The field "g:gender" must be one of: male, female, unisex.',
                    $line
                ));
            }

            $sizeSystem = trim((string) ($googleChildren->size_system ?? ''));
            if ($sizeSystem !== '' && !\in_array($sizeSystem, self::ALLOWED_SIZE_SYSTEM_VALUES, true)) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'size_system',
                    'The field "g:size_system" must be one of: AU, BR, CN, DE, EU, FR, IT, JP, MEX, UK, US.',
                    $line
                ));
            }

            $ageGroup = trim((string) ($googleChildren->age_group ?? ''));
            if ($ageGroup !== '' && !\in_array($ageGroup, self::ALLOWED_AGE_GROUP_VALUES, true)) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'age_group',
                    'The field "g:age_group" must be one of: newborn, infant, toddler, kids, adult.',
                    $line
                ));
            }

            $gtin = trim((string) ($googleChildren->gtin ?? ''));
            $mpn = trim((string) ($googleChildren->mpn ?? ''));
            $identifierExists = trim((string) ($googleChildren->identifier_exists ?? ''));

            if ($gtin === '' && $mpn === '' && strtolower($identifierExists) !== 'no') {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'identifier_exists',
                    'When no g:gtin or g:mpn is provided, g:identifier_exists must be set to "no".',
                    $line
                ));
            }
        }
    }
}
