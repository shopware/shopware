<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemConfigValidator::class)]
class SystemConfigValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @param array<string, array<string, string|int|null>> $inputValues
     * @param array{elements: array{config: array<string, int|string|bool>, name: string}[]} $formConfigs
     */
    #[DataProvider('validateProvider')]
    public function testValidate(array $inputValues, array $formConfigs, bool $expectErrors): void
    {
        $configurationServiceMock = $this->createMock(ConfigurationService::class);
        $validator = new SystemConfigValidator(
            $configurationServiceMock,
            $this->getContainer()->get(DataValidator::class)
        );

        $configurationServiceMock
            ->expects(static::once())
            ->method('getConfiguration')
            ->willReturn($formConfigs);

        $contextMock = Context::createDefaultContext();

        if ($expectErrors) {
            self::expectException(ConstraintViolationException::class);
        }
        $validator->validate($inputValues, $contextMock);
    }

    public static function validateProvider(): \Generator
    {
        yield 'Validate success with required rule' => [
            'inputValues' => [
                'null' => [
                    'dummyField' => 'Dummy Value',
                ],
            ],
            'formConfigs' => [
                [
                    'elements' => [
                        [
                            'name' => 'dummyField',
                            'config' => [
                                'required' => true,
                                'maxLength' => 255,
                            ],
                        ],
                    ],
                ],
            ],
            'expectErrors' => false,
        ];

        yield 'Validate failure with required rule, empty value' => [
            'inputValues' => [
                'null' => [
                    'dummyField' => '',
                ],
            ],
            'formConfigs' => [
                [
                    'elements' => [
                        [
                            'name' => 'dummyField',
                            'config' => [
                                'required' => true,
                                'maxLength' => 255,
                            ],
                        ],
                    ],
                ],
            ],
            'expectErrors' => true,
        ];

        yield 'Validate failure with required rule, empty value, non-default channel' => [
            'inputValues' => [
                '01931ed04f637396a4bdd16bb170933m' => [
                    'dummyField' => '',
                ],
            ],
            'formConfigs' => [
                [
                    'elements' => [
                        [
                            'name' => 'dummyField',
                            'config' => [
                                'required' => true,
                                'maxLength' => 255,
                            ],
                        ],
                    ],
                ],
            ],
            'expectErrors' => true,
        ];

        yield 'Validate success with required rule, null value, non-default channel' => [
            'inputValues' => [
                '01931ed04f637396a4bdd16bb170933m' => [
                    'dummyField' => null,
                ],
            ],
            'formConfigs' => [
                [
                    'elements' => [
                        [
                            'name' => 'dummyField',
                            'config' => [
                                'required' => true,
                                'maxLength' => 255,
                            ],
                        ],
                    ],
                ],
            ],
            'expectErrors' => false,
        ];

        yield 'Validate success without required rule' => [
            'inputValues' => [
                'null' => [
                    'core.basicInformation.dummyKey' => 'Dummy Value',
                ],
            ],
            'formConfigs' => [
                [
                    'elements' => [
                        [
                            'name' => 'core.basicInformation.dummyKey',
                            'config' => [],
                        ],
                    ],
                ],
            ],
            'expectErrors' => false,
        ];

        yield 'Validate success with missing field on form input' => [
            'inputValues' => [
                'null' => [
                    'core.basicInformation.fieldNotFound' => 'Dummy Value',
                ],
            ],
            'formConfigs' => [
                [
                    'elements' => [
                        [
                            'name' => 'core.basicInformation.dummyKey',
                            'config' => [
                                'required' => true,
                                'maxLength' => 255,
                            ],
                        ],
                        [
                            'name' => 'core.basicInformation.fieldNotFound',
                            'config' => [
                                'required' => true,
                                'maxLength' => 255,
                            ],
                        ],
                    ],
                ],
            ],
            'expectErrors' => false,
        ];
    }
}
