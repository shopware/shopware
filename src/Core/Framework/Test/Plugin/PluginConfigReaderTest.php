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
                'title' => [
                    'en_GB' => 'Basic configuration',
                    'de_DE' => 'Grundeinstellungen',
                ],
                'fields' => [
                    0 => [
                        'type' => 'text',
                        'name' => 'email',
                        'label' => [
                            'en_GB' => 'eMail',
                            'de_DE' => 'E-Mail',
                        ],
                        'placeholder' => [
                            'en_GB' => 'Enter your eMail address',
                            'de_DE' => 'Bitte gib deine E-Mail Adresse ein',
                        ],
                        'copyable' => true,
                        'value' => null,
                    ],
                    1 => [
                        'type' => 'select',
                        'name' => 'mailMethod',
                        'label' => [
                            'en_GB' => 'Mailing protocol',
                            'de_DE' => 'E-Mail Versand Protokoll',
                        ],
                        'placeholder' => [
                            'en_GB' => 'Choose your preferred transfer method',
                            'de_DE' => 'Bitte wähle dein bevorzugtes Versand Protokoll',
                        ],
                        'options' => [
                            0 => [
                                'value' => 'smtp',
                                'label' => [
                                    'en_GB' => 'SMTP',
                                ],
                            ],
                            1 => [
                                'value' => 'pop3',
                                'label' => [
                                    'en_GB' => 'POP3',
                                ],
                            ],
                        ],
                        'value' => null,
                    ],
                ],
            ],
        ];
    }
}
