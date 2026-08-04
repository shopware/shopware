<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigCard;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigElement;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigTab;
use Shopware\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator;

/**
 * @internal
 */
#[Package('framework')]
class SystemConfigValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @param array<string, array<string, string|null>> $inputValues
     * @param list<SystemConfigTab> $formConfigs
     */
    #[DataProvider('validateProvider')]
    public function testValidate(array $inputValues, array $formConfigs, bool $expectErrors): void
    {
        $systemConfigDefinitionServiceMock = $this->createMock(SystemConfigDefinitionService::class);
        $validator = new SystemConfigValidator(
            $systemConfigDefinitionServiceMock,
            self::getContainer()->get(DataValidator::class)
        );

        $systemConfigDefinitionServiceMock
            ->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($formConfigs);

        $contextMock = Context::createDefaultContext();

        if ($expectErrors) {
            $this->expectException(ConstraintViolationException::class);
        }
        $validator->validate($inputValues, $contextMock);
    }

    /**
     * @return \Generator<string, array{
     *     inputValues: array<string, array<string, string|null>>,
     *     formConfigs: list<SystemConfigTab>,
     *     expectErrors: bool
     * }>
     */
    public static function validateProvider(): \Generator
    {
        $dummyFieldTab = new SystemConfigTab(
            [
                new SystemConfigCard(
                    [
                        new SystemConfigElement(
                            'dummyField',
                            [
                                'required' => true,
                                'maxLength' => 255,
                            ],
                            'text',
                        ),
                    ],
                    [
                        'en-GB' => 'Dummy field',
                        'de-DE' => 'Dummy field',
                    ]
                ),
            ]
        );

        yield 'Validate success with required rule' => [
            'inputValues' => [
                'null' => [
                    'dummyField' => 'Dummy Value',
                ],
            ],
            'formConfigs' => [
                $dummyFieldTab,
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
                $dummyFieldTab,
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
                $dummyFieldTab,
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
                $dummyFieldTab,
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
                new SystemConfigTab(
                    [
                        new SystemConfigCard(
                            [
                                new SystemConfigElement(
                                    'core.basicInformation.dummyKey',
                                    [],
                                    'text',
                                ),
                            ],
                            [
                                'en-GB' => 'Basic configuration',
                                'de-DE' => 'Grundeinstellungen',
                            ]
                        ),
                    ]
                ),
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
                new SystemConfigTab(
                    [
                        new SystemConfigCard(
                            [
                                new SystemConfigElement(
                                    'core.basicInformation.dummyKey',
                                    [
                                        'required' => true,
                                        'maxLength' => 255,
                                    ],
                                    'text',
                                ),
                                new SystemConfigElement(
                                    'core.basicInformation.fieldNotFound',
                                    [
                                        'required' => true,
                                        'maxLength' => 255,
                                    ],
                                    'text',
                                ),
                            ],
                            [
                                'en-GB' => 'Basic configuration',
                                'de-DE' => 'Grundeinstellungen',
                            ]
                        ),
                    ]
                ),
            ],
            'expectErrors' => false,
        ];
    }
}
