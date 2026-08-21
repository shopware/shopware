<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigCard;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigElement;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigTab;
use Shopware\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemConfigValidator::class)]
class SystemConfigValidatorTest extends TestCase
{
    /**
     * @param array<string, mixed> $inputValues
     * @param list<SystemConfigTab> $formConfigs
     */
    #[DataProvider('dataProviderTestValidateSuccess')]
    public function testValidateSuccess(array $inputValues, array $formConfigs): void
    {
        $exceptionThrown = false;

        $systemConfigDefinitionServiceMock = static::createStub(SystemConfigDefinitionService::class);
        $systemConfigDefinitionServiceMock->method('getConfiguration')
            ->willReturn($formConfigs);

        $dataValidatorMock = static::createStub(DataValidator::class);

        $systemConfigValidation = new SystemConfigValidator($systemConfigDefinitionServiceMock, $dataValidatorMock);

        $contextMock = Context::createDefaultContext();

        try {
            $systemConfigValidation->validate($inputValues, $contextMock);
        } catch (ConstraintViolationException $exception) {
            $exceptionThrown = true;
        }

        static::assertFalse($exceptionThrown);
    }

    /**
     * @param array<string, mixed> $inputValues
     * @param list<SystemConfigTab> $formConfigs
     */
    #[DataProvider('dataProviderTestValidateFailure')]
    public function testValidateFailure(array $inputValues, array $formConfigs): void
    {
        $systemConfigDefinitionServiceMock = static::createStub(SystemConfigDefinitionService::class);
        $systemConfigDefinitionServiceMock->method('getConfiguration')
            ->willReturn($formConfigs);

        $validateException = static::createStub(ConstraintViolationException::class);

        $dataValidatorMock = static::createStub(DataValidator::class);
        $dataValidatorMock->method('validate')
            ->willThrowException($validateException);

        $systemConfigValidation = new SystemConfigValidator($systemConfigDefinitionServiceMock, $dataValidatorMock);

        $contextMock = Context::createDefaultContext();

        $this->expectException(ConstraintViolationException::class);

        $systemConfigValidation->validate($inputValues, $contextMock);
    }

    /**
     * @param array<string, mixed> $inputValues
     * @param list<SystemConfigTab> $formConfigs
     */
    #[DataProvider('dataProviderTestValidateSuccess')]
    public function testValidateWithEmptyConfig(array $inputValues, array $formConfigs): void
    {
        $exceptionThrown = false;

        $systemConfigDefinitionServiceMock = static::createStub(SystemConfigDefinitionService::class);
        $systemConfigDefinitionServiceMock->method('getConfiguration')
            ->willReturn([]);

        $dataValidatorMock = static::createStub(DataValidator::class);

        $systemConfigValidation = new SystemConfigValidator($systemConfigDefinitionServiceMock, $dataValidatorMock);

        $contextMock = Context::createDefaultContext();

        try {
            $systemConfigValidation->validate($inputValues, $contextMock);
        } catch (ConstraintViolationException $exception) {
            $exceptionThrown = true;
        }

        static::assertFalse($exceptionThrown);
    }

    public function testValidateUsesConfigurationDomainForNestedKeys(): void
    {
        $context = Context::createDefaultContext();

        $systemConfigDefinitionServiceMock = $this->createMock(SystemConfigDefinitionService::class);
        $systemConfigDefinitionServiceMock
            ->expects($this->once())
            ->method('getConfiguration')
            ->with('core.basicInformation', $context)
            ->willReturn([
                new SystemConfigTab([
                    new SystemConfigCard(
                        [
                            new SystemConfigElement('core.basicInformation.foo', []),
                        ],
                        []
                    ),
                ]),
            ]);

        $dataValidatorMock = $this->createMock(DataValidator::class);
        $dataValidatorMock
            ->expects($this->once())
            ->method('validate');

        $systemConfigValidation = new SystemConfigValidator($systemConfigDefinitionServiceMock, $dataValidatorMock);

        $systemConfigValidation->validate([
            'null' => [
                'core.basicInformation.foo.bar.baz' => 'test-value',
            ],
        ], $context);
    }

    public function testValidateAddsNoConstraintsForDomainWithoutConfiguration(): void
    {
        $systemConfigDefinitionServiceMock = static::createStub(SystemConfigDefinitionService::class);
        $systemConfigDefinitionServiceMock->method('getConfiguration')
            ->willReturn([]);

        $definition = null;
        $systemConfigValidation = new SystemConfigValidator(
            $systemConfigDefinitionServiceMock,
            $this->createDefinitionCapturingValidator($definition)
        );

        $systemConfigValidation->validate(
            ['null' => ['dummy.domain.dummyKey' => 'Dummy Value']],
            Context::createDefaultContext()
        );

        static::assertInstanceOf(DataValidationDefinition::class, $definition);
        static::assertSame([], $definition->getSubDefinitions());
    }

    public function testValidateIgnoresSystemConfigExceptionsWhileLoadingTheDomainConfiguration(): void
    {
        $systemConfigDefinitionServiceMock = static::createStub(SystemConfigDefinitionService::class);
        $systemConfigDefinitionServiceMock->method('getConfiguration')
            ->willThrowException(SystemConfigException::configurationNotFound('missing'));

        $definition = null;
        $systemConfigValidation = new SystemConfigValidator(
            $systemConfigDefinitionServiceMock,
            $this->createDefinitionCapturingValidator($definition)
        );

        $systemConfigValidation->validate(
            ['null' => ['dummy.domain.dummyKey' => 'Dummy Value']],
            Context::createDefaultContext()
        );

        static::assertInstanceOf(DataValidationDefinition::class, $definition);
        static::assertSame([], $definition->getSubDefinitions());
    }

    /**
     * @param array<string, mixed> $elementConfig
     * @param array<int, mixed> $expected
     */
    #[DataProvider('dataProviderTestGetRuleByKey')]
    public function testValidateBuildsConstraintsFromElementConfig(array $elementConfig, array $expected, bool $allowNulls): void
    {
        // nulls are only valid values for sales channel specific configuration
        $salesChannelId = $allowNulls ? Uuid::randomHex() : 'null';
        $configKey = 'core.basicInformation.dummyKey';

        $systemConfigDefinitionServiceMock = static::createStub(SystemConfigDefinitionService::class);
        $systemConfigDefinitionServiceMock->method('getConfiguration')
            ->willReturn([
                new SystemConfigTab(
                    [
                        new SystemConfigCard(
                            [
                                new SystemConfigElement($configKey, $elementConfig),
                            ],
                            []
                        ),
                    ]
                ),
            ]);

        $definition = null;
        $systemConfigValidation = new SystemConfigValidator(
            $systemConfigDefinitionServiceMock,
            $this->createDefinitionCapturingValidator($definition)
        );

        $systemConfigValidation->validate(
            [$salesChannelId => [$configKey => 'Dummy Value']],
            Context::createDefaultContext()
        );

        static::assertInstanceOf(DataValidationDefinition::class, $definition);
        $subDefinition = $definition->getSubDefinitions()[$salesChannelId] ?? null;
        static::assertInstanceOf(DataValidationDefinition::class, $subDefinition);
        static::assertSame([$configKey], array_keys($subDefinition->getProperties()));
        static::assertEquals($expected, $subDefinition->getProperty($configKey));
    }

    public static function dataProviderTestGetRuleByKey(): \Generator
    {
        yield 'element config is empty' => [
            'elementConfig' => [],
            'expected' => [],
            'allowNulls' => false,
        ];

        yield 'element config with type string' => [
            'elementConfig' => [
                'required' => true,
                'dataType' => 'string',
                'minLength' => 1,
                'maxLength' => 255,
            ],
            'expected' => [
                new Assert\Length(min: 1),
                new Assert\Length(max: 255),
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ],
            'allowNulls' => false,
        ];

        yield 'element config with type int' => [
            'elementConfig' => [
                'required' => true,
                'dataType' => 'int',
                'min' => 1,
                'max' => 100,
            ],
            'expected' => [
                new Assert\Range(min: 1),
                new Assert\Range(max: 100),
                new Assert\Type('int'),
                new Assert\NotBlank(),
            ],
            'allowNulls' => false,
        ];

        yield 'element config with type string, nulls allowed' => [
            'elementConfig' => [
                'required' => true,
                'dataType' => 'string',
                'minLength' => 1,
                'maxLength' => 255,
            ],
            'expected' => [
                new Assert\Length(min: 1),
                new Assert\Length(max: 255),
                new Assert\Type('string'),
                new Assert\NotBlank(null, null, true),
            ],
            'allowNulls' => true,
        ];

        yield 'element config with string values for minLength and maxLength' => [
            'elementConfig' => [
                'required' => false,
                'dataType' => 'string',
                'minLength' => '5',
                'maxLength' => '100',
            ],
            'expected' => [
                new Assert\Length(min: 5),
                new Assert\Length(max: 100),
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ],
            'allowNulls' => false,
        ];
    }

    public static function dataProviderTestValidateSuccess(): \Generator
    {
        yield 'Validate success with required rule' => [
            'inputValues' => [
                'null' => [
                    'Dummy Key' => 'Dummy Value',
                ],
            ],
            'formConfigs' => [
                new SystemConfigTab(
                    [
                        new SystemConfigCard(
                            [
                                new SystemConfigElement('Dummy Name', [
                                    'required' => true,
                                    'maxLength' => 255,
                                ]),
                            ],
                            []
                        ),
                    ]
                ),
            ],
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
                                new SystemConfigElement('core.basicInformation.dummyKey', []),
                            ],
                            []
                        ),
                    ]
                ),
            ],
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
                                new SystemConfigElement('core.basicInformation.dummyKey', [
                                    'required' => true,
                                    'maxLength' => 255,
                                ]),
                                new SystemConfigElement('core.basicInformation.fieldNotFound', [
                                    'required' => true,
                                    'maxLength' => 255,
                                ]),
                            ],
                            []
                        ),
                    ]
                ),
            ],
        ];
    }

    public static function dataProviderTestValidateFailure(): \Generator
    {
        yield 'Validate failure with required rule' => [
            'inputValues' => [
                'null' => [
                    'core.basicInformation.dummyField' => null,
                ],
            ],
            'formConfigs' => [
                new SystemConfigTab(
                    [
                        new SystemConfigCard(
                            [
                                new SystemConfigElement('core.basicInformation.dummyField', [
                                    'required' => true,
                                    'maxLength' => 255,
                                ]),
                            ],
                            []
                        ),
                    ]
                ),
            ],
        ];
    }

    /**
     * @param-out DataValidationDefinition|null $definition
     */
    private function createDefinitionCapturingValidator(?DataValidationDefinition &$definition): DataValidator
    {
        $dataValidatorMock = $this->createMock(DataValidator::class);
        $dataValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (array $data, DataValidationDefinition $passedDefinition) use (&$definition): void {
                $definition = $passedDefinition;
            });

        return $dataValidatorMock;
    }
}
