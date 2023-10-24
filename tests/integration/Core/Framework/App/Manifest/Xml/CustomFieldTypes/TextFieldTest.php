<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class TextFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $textField = $this->importCustomField(__DIR__ . '/_fixtures/text-field.xml');

        static::assertSame('test_text_field', $textField->getName());
        static::assertSame('text', $textField->getType());
        static::assertTrue($textField->isActive());
        static::assertEquals([
            'type' => 'text',
            'label' => [
                'en-GB' => 'Test text field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Enter a text...',
            ],
            'componentName' => 'sw-field',
            'customFieldType' => 'text',
            'customFieldPosition' => 1,
        ], $textField->getConfig());
    }
}
