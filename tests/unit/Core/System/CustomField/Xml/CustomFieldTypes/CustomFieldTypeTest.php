<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\Xml\CustomFieldTypes\CustomFieldType;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomFieldType::class)]
class CustomFieldTypeTest extends TestCase
{
    public function testToEntityPayloadAddsTranslationsForTheDefaultLocale(): void
    {
        $payload = $this->getSingleSelectField()->toEntityPayload('de-AT');

        static::assertSame([
            'label' => [
                'en-GB' => 'Test single-select field',
                'de-AT' => 'Test single-select field',
            ],
            'helpText' => [],
            'customFieldPosition' => 1,
            'placeholder' => [
                'en-GB' => 'Choose an option...',
                'de-AT' => 'Choose an option...',
            ],
            'componentName' => 'sw-single-select',
            'customFieldType' => 'select',
            'options' => [
                [
                    'label' => [
                        'en-GB' => 'First',
                        'de-DE' => 'Erster',
                        'de-AT' => 'Erster',
                    ],
                    'value' => 'first',
                ],
                [
                    'label' => [
                        'en-GB' => 'Second',
                        'de-AT' => 'Second',
                    ],
                    'value' => 'second',
                ],
            ],
        ], $payload['config']);
    }

    public function testToEntityPayloadKeepsTranslationsAsDeclaredWithoutDefaultLocale(): void
    {
        $payload = $this->getSingleSelectField()->toEntityPayload();

        static::assertSame([
            'label' => [
                'en-GB' => 'Test single-select field',
            ],
            'helpText' => [],
            'customFieldPosition' => 1,
            'placeholder' => [
                'en-GB' => 'Choose an option...',
            ],
            'componentName' => 'sw-single-select',
            'customFieldType' => 'select',
            'options' => [
                [
                    'label' => [
                        'en-GB' => 'First',
                        'de-DE' => 'Erster',
                    ],
                    'value' => 'first',
                ],
                [
                    'label' => [
                        'en-GB' => 'Second',
                    ],
                    'value' => 'second',
                ],
            ],
        ], $payload['config']);
    }

    private function getSingleSelectField(): CustomFieldType
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/single-select-field.xml');
        $customFields = $manifest->getCustomFields();
        static::assertNotNull($customFields);

        return $customFields->getCustomFieldSets()[0]->getFields()[0];
    }
}
