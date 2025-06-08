<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class TextAreaFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $textAreaField = $this->importCustomField(__DIR__ . '/_fixtures/text-area-field.xml');

        static::assertSame('test_text_area_field', $textAreaField->getName());
        static::assertSame('html', $textAreaField->getType());
        static::assertTrue($textAreaField->isActive());
        static::assertEquals([
            'label' => [
                'en-GB' => 'Test text-area field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Enter a text...',
            ],
            'componentName' => 'sw-text-editor',
            'customFieldType' => 'textEditor',
            'customFieldPosition' => 1,
        ], $textAreaField->getConfig());
    }
}
