<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Requirements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Requirements\ConfigurationRequirementsValidator;
use Shopware\Core\Installer\Requirements\IniConfigReader;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Shopware\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 */
#[CoversClass(ConfigurationRequirementsValidator::class)]
class ConfigurationRequirementsValidatorTest extends TestCase
{
    private MockObject&IniConfigReader $configReader;

    private ConfigurationRequirementsValidator $validator;

    protected function setUp(): void
    {
        $this->configReader = $this->createMock(IniConfigReader::class);
        $this->validator = new ConfigurationRequirementsValidator($this->configReader);
    }

    /**
     * @param array<string, string> $iniValues
     * @param SystemCheck[] $expectedChecks
     */
    #[DataProvider('configRequirements')]
    public function testValidateRequirements(array $iniValues, array $expectedChecks): void
    {
        $this->configReader->method('get')->willReturnCallback(
            fn ($arg) => $iniValues[$arg] ?? ''
        );

        $checks = $this->validator->validateRequirements(new RequirementsCheckCollection());

        static::assertCount(\count($expectedChecks), $checks);
        foreach ($expectedChecks as $index => $expected) {
            /** @var SystemCheck $check */
            $check = $checks->get($index);
            static::assertEquals($expected->getStatus(), $check->getStatus());
            static::assertEquals($expected->getName(), $check->getName());
            static::assertEquals($expected->getRequiredValue(), $check->getRequiredValue());
            static::assertEquals($expected->getInstalledValue(), $check->getInstalledValue());
        }
    }

    public static function configRequirements(): \Generator
    {
        yield 'all checks pass with minimum requirements' => [
            [
                'max_execution_time' => '30',
                'memory_limit' => '512M',
                'opcache.memory_consumption' => '256',
            ],
            [
                new SystemCheck('max_execution_time', RequirementCheck::STATUS_SUCCESS, '30', '30'),
                new SystemCheck('memory_limit', RequirementCheck::STATUS_SUCCESS, '512M', '512M'),
                new SystemCheck('opcache.memory_consumption', RequirementCheck::STATUS_SUCCESS, '256M', '256M'),
            ],
        ];

        yield 'all checks pass with higher configs' => [
            [
                'max_execution_time' => '60',
                'memory_limit' => '1024M',
                'opcache.memory_consumption' => '512',
            ],
            [
                new SystemCheck('max_execution_time', RequirementCheck::STATUS_SUCCESS, '30', '60'),
                new SystemCheck('memory_limit', RequirementCheck::STATUS_SUCCESS, '512M', '1024M'),
                new SystemCheck('opcache.memory_consumption', RequirementCheck::STATUS_SUCCESS, '256M', '512M'),
            ],
        ];

        yield 'all checks fail with lower configs' => [
            [
                'max_execution_time' => '29',
                'memory_limit' => '511M',
                'opcache.memory_consumption' => '255',
            ],
            [
                new SystemCheck('max_execution_time', RequirementCheck::STATUS_ERROR, '30', '29'),
                new SystemCheck('memory_limit', RequirementCheck::STATUS_ERROR, '512M', '511M'),
                new SystemCheck('opcache.memory_consumption', RequirementCheck::STATUS_WARNING, '256M', '255M'),
            ],
        ];

        yield 'one check fails with lower configs' => [
            [
                'max_execution_time' => '29',
                'memory_limit' => '512M',
                'opcache.memory_consumption' => '256',
            ],
            [
                new SystemCheck('max_execution_time', RequirementCheck::STATUS_ERROR, '30', '29'),
                new SystemCheck('memory_limit', RequirementCheck::STATUS_SUCCESS, '512M', '512M'),
                new SystemCheck('opcache.memory_consumption', RequirementCheck::STATUS_SUCCESS, '256M', '256M'),
            ],
        ];

        yield 'Opcache is not configured' => [
            [
                'max_execution_time' => '30',
                'memory_limit' => '512M',
                'opcache.memory_consumption' => '',
            ],
            [
                new SystemCheck('max_execution_time', RequirementCheck::STATUS_SUCCESS, '30', '30'),
                new SystemCheck('memory_limit', RequirementCheck::STATUS_SUCCESS, '512M', '512M'),
                new SystemCheck('opcache.memory_consumption', RequirementCheck::STATUS_WARNING, '256M', '0M'),
            ],
        ];

        yield 'Checks pass with unlimited values' => [
            [
                'max_execution_time' => '0',
                'memory_limit' => '-1',
                'opcache.memory_consumption' => '',
            ],
            [
                new SystemCheck('max_execution_time', RequirementCheck::STATUS_SUCCESS, '30', '0'),
                new SystemCheck('memory_limit', RequirementCheck::STATUS_SUCCESS, '512M', '-1'),
                new SystemCheck('opcache.memory_consumption', RequirementCheck::STATUS_WARNING, '256M', '0M'),
            ],
        ];
    }
}
