<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @package system-settings
 *
 * @internal
 */
#[CoversClass(SystemConfigValidator::class)]
class SystemConfigValidatorTest extends TestCase
{
    /**
     * @param array<string, mixed> $inputValues
     * @param array<string, mixed> $formConfigs
     */
    #[DataProvider('dataProviderTestValidateSuccess')]
    public function testValidateSuccess(array $inputValues, array $formConfigs): void
    {
        $configurationServiceMock = $this->createMock(ConfigurationService::class);
        $configurationServiceMock->method('getConfiguration')
            ->willReturn($formConfigs);

        $dataValidatorMock = $this->createMock(DataValidator::class);

        $systemConfigValidation = new SystemConfigValidator($configurationServiceMock, $dataValidatorMock);

        $contextMock = Context::createDefaultContext();

        $systemConfigValidation->validate($inputValues, $contextMock);

        static::assertTrue(true);
    }

    /**
     * @param array<string, mixed> $inputValues
     * @param array<string, mixed> $formConfigs
     */
    #[DataProvider('dataProviderTestValidateFailure')]
    public function testValidateFailure(array $inputValues, array $formConfigs): void
    {
        $configurationServiceMock = $this->createMock(ConfigurationService::class);
        $configurationServiceMock->method('getConfiguration')
            ->willReturn($formConfigs);

        $validateException = $this->createMock(ConstraintViolationException::class);

        $dataValidatorMock = $this->createMock(DataValidator::class);
        $dataValidatorMock->method('validate')
            ->willThrowException($validateException);

        $systemConfigValidation = new SystemConfigValidator($configurationServiceMock, $dataValidatorMock);

        $contextMock = Context::createDefaultContext();

        $this->expectException(ConstraintViolationException::class);

        $systemConfigValidation->validate($inputValues, $contextMock);
    }

    /**
     * @param array<string, mixed> $inputValues
     */
    #[DataProvider('dataProviderTestValidateSuccess')]
    public function testValidateWithEmptyConfig(array $inputValues): void
    {
        $configurationServiceMock = $this->createMock(ConfigurationService::class);
        $configurationServiceMock->method('getConfiguration')
            ->willReturn([]);

        $dataValidatorMock = $this->createMock(DataValidator::class);

        $systemConfigValidation = new SystemConfigValidator($configurationServiceMock, $dataValidatorMock);

        $contextMock = Context::createDefaultContext();

        $systemConfigValidation->validate($inputValues, $contextMock);

        static::assertTrue(true);
    }

    public function testGetSystemConfigByDomainEmptyDomain(): void
    {
        $configurationServiceMock = $this->createMock(ConfigurationService::class);
        $dataValidatorMock = $this->createMock(DataValidator::class);

        $systemConfigValidation = new SystemConfigValidator($configurationServiceMock, $dataValidatorMock);

        $contextMock = Context::createDefaultContext();

        $refMethod = ReflectionHelper::getMethod(SystemConfigValidator::class, 'getSystemConfigByDomain');

        $result = $refMethod->invoke($systemConfigValidation, 'dummy domain', $contextMock);

        static::assertEquals([], $result);
    }

    public function testGetSystemConfigByDomainWithException(): void
    {
        $configurationServiceMock = $this->createMock(ConfigurationService::class);
        $configurationServiceMock->method('getConfiguration')
            ->willThrowException($this->createMock(BundleConfigNotFoundException::class));

        $dataValidatorMock = $this->createMock(DataValidator::class);

        $systemConfigValidation = new SystemConfigValidator($configurationServiceMock, $dataValidatorMock);

        $contextMock = Context::createDefaultContext();

        $refMethod = ReflectionHelper::getMethod(SystemConfigValidator::class, 'getSystemConfigByDomain');

        $result = $refMethod->invoke($systemConfigValidation, 'dummy domain', $contextMock);

        static::assertEquals($result, []);
    }

    /**
     * @param array<string, mixed> $elementConfig
     * @param array<int, mixed> $expected
     */
    #[DataProvider('dataProviderTestGetRuleByKey')]
    public function testBuildConstraintsWithConfigs(array $elementConfig, array $expected): void
    {
        $configurationServiceMock = $this->createMock(ConfigurationService::class);
        $dataValidatorMock = $this->createMock(DataValidator::class);

        $systemConfigValidation = new SystemConfigValidator($configurationServiceMock, $dataValidatorMock);

        $refMethod = ReflectionHelper::getMethod(SystemConfigValidator::class, 'buildConstraintsWithConfigs');

        $result = $refMethod->invoke($systemConfigValidation, $elementConfig);

        static::assertEquals($expected, $result);
    }

    public static function dataProviderTestGetRuleByKey(): \Generator
    {
        yield 'element config is empty' => [
            'elementConfig' => [],
            'expected' => [],
        ];

        yield 'element config with type string' => [
            'elementConfig' => [
                'required' => true,
                'dataType' => 'string',
                'minLength' => 1,
                'maxLength' => 255,
            ],
            'expected' => [
                new Assert\Length(['min' => 1]),
                new Assert\Length(['max' => 255]),
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ],
        ];

        yield 'element config with type int' => [
            'elementConfig' => [
                'required' => true,
                'dataType' => 'int',
                'min' => 1,
                'max' => 100,
            ],
            'expected' => [
                new Assert\Range(['min' => 1]),
                new Assert\Range(['max' => 100]),
                new Assert\Type('int'),
                new Assert\NotBlank(),
            ],
        ];
    }

    public static function dataProviderTestValidateSuccess(): \Generator
    {
        yield 'Validate success with required rule' => [
            'input values' => [
                'null' => [
                    'Dummy Key' => 'Dummy Value',
                ],
            ],
            'form configs' => [
                [
                    'elements' => [
                        [
                            'name' => 'Dummy Name',
                            'config' => [
                                'required' => true,
                                'maxLength' => 255,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'Validate success without required rule' => [
            'input values' => [
                'null' => [
                    'core.basicInformation.dummyKey' => 'Dummy Value',
                ],
            ],
            'form configs' => [
                [
                    'elements' => [
                        [
                            'name' => 'core.basicInformation.dummyKey',
                            'config' => [],
                        ],
                    ],
                ],
            ],
        ];

        yield 'Validate success with missing field on form input' => [
            'input values' => [
                'null' => [
                    'core.basicInformation.fieldNotFound' => 'Dummy Value',
                ],
            ],
            'form configs' => [
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
        ];
    }

    public static function dataProviderTestValidateFailure(): \Generator
    {
        yield 'Validate failure with required rule' => [
            'input values' => [
                'null' => [
                    'core.basicInformation.dummyField' => null,
                ],
            ],
            'form configs' => [
                [
                    'elements' => [
                        [
                            'name' => 'core.basicInformation.dummyField',
                            'config' => [
                                'required' => true,
                                'maxLength' => 255,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
