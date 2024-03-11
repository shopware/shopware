<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class MediaSelectionFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $mediaSelectionField = $this->importCustomField(__DIR__ . '/_fixtures/media-selection-field.xml');

        static::assertSame('test_media_selection_field', $mediaSelectionField->getName());
        static::assertSame('text', $mediaSelectionField->getType());
        static::assertTrue($mediaSelectionField->isActive());
        static::assertEquals([
            'label' => [
                'en-GB' => 'Test media-selection field',
            ],
            'helpText' => [],
            'componentName' => 'sw-media-field',
            'customFieldType' => 'media',
            'customFieldPosition' => 1,
        ], $mediaSelectionField->getConfig());
    }
}
