<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Exception\XmlParsingException;
use Shopware\Core\Framework\Plugin\Helper\PluginConfigReader;

class PluginConfigReaderTest extends TestCase
{
    private const VALID_CONFIG_PATH = __DIR__ . '/_fixtures/valid_config.xml';
    private const INVALID_CONFIG_PATH = __DIR__ . '/_fixtures/invalid_config.xml';

    public function test_parse_file_with_valid_config_xml(): void
    {
        $reader = new PluginConfigReader();
        $actualResult = $reader->read(self::VALID_CONFIG_PATH);
        $expectedResult = $this->getValidResult();

        self::assertSame($expectedResult, $actualResult);
    }

    public function test_expect_invalid_argument_exception_with_invalid_xml(): void
    {
        $reader = new PluginConfigReader();
        $this->expectException(XmlParsingException::class);
        $reader->read(self::INVALID_CONFIG_PATH);
    }

    private function getValidResult(): array
    {
        return [
            0 => [
                'type' => 'text',
                'label' => [
                    'en_GB' => 'Salutation',
                    'de_DE' => 'Begrüßung',
                ],
                'helpText' => [
                    'en_GB' => 'The salutation shown in every eMail',
                    'de_DE' => 'Die Begrüßung in jeder eMail',
                ],
            ],
            1 => [
                'type' => 'textarea',
                'label' => [
                    'en_GB' => 'eMail body',
                    'de_DE' => 'eMail Inhalt',
                ],
                'copyable' => 'true',
            ],
        ];
    }
}
