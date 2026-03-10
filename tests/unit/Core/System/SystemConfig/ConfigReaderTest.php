<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigReader::class)]
class ConfigReaderTest extends TestCase
{
    private ConfigReader $configReader;

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
        $this->expectException(UtilException::class);

        $this->configReader->read(__DIR__ . '/config.xml');
    }

    public function testConfigReaderWithInvalidConfig(): void
    {
        $this->expectException(UtilException::class);

        $this->configReader->read(__DIR__ . '/_fixtures/invalid_config.xml');
    }

    /**
     * @return array<mixed>
     */
    private function getExpectedConfig(): array
    {
        return [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [
                    [
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
                        'defaultValue' => '42',
                    ],
                    [
                        'type' => 'text',
                        'name' => 'stringWithQuoteDefaultValueRemovesQuotes',
                        'defaultValue' => '42',
                    ],
                    [
                        'type' => 'text',
                        'name' => 'nullDefault',
                        'defaultValue' => null,
                    ],
                    [
                        'type' => 'int',
                        'name' => 'int',
                        'defaultValue' => 42,
                    ],
                    [
                        'type' => 'float',
                        'name' => 'float',
                        'defaultValue' => 42.0,
                    ],
                    [
                        'type' => 'float',
                        'name' => 'floatWithStringValueExpectsValueIsCastedToFloat',
                        'defaultValue' => 42.5,
                    ],
                    [
                        'type' => 'bool',
                        'name' => 'bool',
                        'defaultValue' => true,
                    ],
                    [
                        'type' => 'single-select',
                        'name' => 'mailMethod',
                        'options' => [
                            [
                                'id' => 'smtp',
                                'name' => [
                                    'en-GB' => 'SMTP',
                                ],
                            ],
                            [
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
                        'defaultValue' => 'smtp',
                    ],
                    [
                        'type' => 'single-select',
                        'name' => 'period',
                        'options' => [
                            [
                                'id' => '30',
                                'name' => [
                                    'en-GB' => '1 Month',
                                ],
                            ],
                            [
                                'id' => '60',
                                'name' => [
                                    'en-GB' => '2 Months',
                                ],
                            ],
                        ],
                        'defaultValue' => '30',
                    ],
                    [
                        'componentName' => 'sw-select',
                        'name' => 'mailMethodComponent',
                        'disabled' => true,
                        'options' => [
                            [
                                'id' => 'smtp',
                                'name' => [
                                    'en-GB' => 'English smtp',
                                    'de-DE' => 'German smtp',
                                ],
                            ],
                            [
                                'id' => 'pop3',
                                'name' => [
                                    'en-GB' => 'English pop3',
                                    'de-DE' => 'German pop3',
                                ],
                            ],
                        ],
                        'defaultValue' => 'pop3',
                    ],
                ],
            ],
        ];
    }
}
