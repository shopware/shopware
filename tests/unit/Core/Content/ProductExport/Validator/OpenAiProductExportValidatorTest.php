<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Validator\JsonlRowParser;
use Shopware\Core\Content\ProductExport\Validator\OpenAiProductExportValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(OpenAiProductExportValidator::class)]
class OpenAiProductExportValidatorTest extends TestCase
{
    public function testValidateAddsErrorForMissingRequiredUrlField(): void
    {
        $entity = new ProductExportEntity();
        $entity->setId('test-export');
        $entity->setProvider('open-ai');
        $entity->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);

        $content = json_encode([
            'is_eligible_search' => true,
            'is_eligible_checkout' => false,
            'item_id' => 'SKU-1',
            'title' => 'Example',
            'description' => 'Example description',
            'url' => 'https://example.com/product',
            'brand' => 'ACME',
            'image_url' => 'https://example.com/image.jpg',
            'price' => '10.99 EUR',
            'availability' => 'in_stock',
            'group_id' => 'group-1',
            'listing_has_variations' => false,
            'seller_name' => 'Merchant',
            'seller_url' => 'https://example.com',
            'target_countries' => ['DE'],
            'store_country' => 'DE',
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES) . \PHP_EOL;

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(1, $errors);
        $firstError = $errors->first();

        static::assertNotNull($firstError);
        static::assertSame('provider-validation-failed', $firstError->getMessageKey());
        static::assertSame('return_policy', $firstError->getParameters()['field']);
    }
}
