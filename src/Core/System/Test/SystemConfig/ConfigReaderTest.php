<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Shopware\Core\System\SystemConfig\Helper\ConfigReader;

class ConfigReaderTest extends TestCase
{
    /**
     * @var ConfigReader
     */
    private $configReader;

    protected function setUp(): void
    {
        $this->configReader = new ConfigReader();
    }

    public function testConfigReaderWithValidConfig(): void
    {
        $actualConfig = $this->configReader->read(__DIR__ . '/_fixtures/valid_config.xml');

        static::assertSame($this->getExpectedConfig(), $actualConfig);
    }

    public function testConfigReaderWithInvalidPath(): void
    {
        $this->expectException(XmlParsingException::class);

        $this->configReader->read(__DIR__ . '/config.xml');
    }

    public function testConfigReaderWithInvalidConfig(): void
    {
        $this->expectException(XmlParsingException::class);

        $this->configReader->read(__DIR__ . '/_fixtures/invalid_config.xml');
    }

    private function getExpectedConfig(): array
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
                        'copyable' => true,
                        'label' => [
                            'en_GB' => 'eMail',
                            'de_DE' => 'E-Mail',
                        ],
                        'placeholder' => [
                            'en_GB' => 'Enter your eMail address',
                            'de_DE' => 'Bitte gib deine E-Mail Adresse ein',
                        ],
                        'value' => null,
                    ],
                    1 => [
                        'type' => 'select',
                        'name' => 'mailMethod',
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
                        'label' => [
                            'en_GB' => 'Mailing protocol',
                            'de_DE' => 'E-Mail Versand Protokoll',
                        ],
                        'placeholder' => [
                            'en_GB' => 'Choose your preferred transfer method',
                            'de_DE' => 'Bitte wähle dein bevorzugtes Versand Protokoll',
                        ],
                        'value' => null,
                    ],
                ],
            ],
        ];
    }
}
