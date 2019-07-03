<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

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
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [
                    0 => [
                        'type' => 'text',
                        'name' => 'email',
                        'copyable' => true,
                        'label' => [
                            'en-GB' => 'eMail',
                            'de-DE' => 'E-Mail',
                        ],
                        'placeholder' => [
                            'en-GB' => 'Enter your eMail address',
                            'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                        ],
                    ],
                    1 => [
                        'type' => 'single-select',
                        'name' => 'mailMethod',
                        'options' => [
                            0 => [
                                'id' => 'smtp',
                                'name' => [
                                    'en-GB' => 'SMTP',
                                ],
                            ],
                            1 => [
                                'id' => 'pop3',
                                'name' => [
                                    'en-GB' => 'POP3',
                                ],
                            ],
                        ],
                        'label' => [
                            'en-GB' => 'Mailing protocol',
                            'de-DE' => 'E-Mail Versand Protokoll',
                        ],
                        'placeholder' => [
                            'en-GB' => 'Choose your preferred transfer method',
                            'de-DE' => 'Bitte wähle dein bevorzugtes Versand Protokoll',
                        ],
                    ],
                ],
            ],
        ];
    }
}
