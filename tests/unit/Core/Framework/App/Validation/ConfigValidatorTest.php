<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Validation\ConfigValidator;
use Shopware\Core\Framework\App\Validation\Error\ConfigurationError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigValidator::class)]
class ConfigValidatorTest extends TestCase
{
    public function testAppWithoutConfigurationProducesNoError(): void
    {
        $errors = $this->createValidator(new StaticFilesystem())->validate(ManifestFixture::empty(), null);

        static::assertCount(0, $errors);
    }

    public function testAllowedComponentsProduceNoError(): void
    {
        $validator = $this->createValidator(new Filesystem(__DIR__ . '/_fixtures/withAllowedComponents'));

        static::assertCount(0, $validator->validate(ManifestFixture::empty(), null));
    }

    public function testDisallowedComponentIsReported(): void
    {
        $validator = $this->createValidator(new Filesystem(__DIR__ . '/_fixtures/withInvalidConfig'));

        $errors = $validator->validate(ManifestFixture::empty(), null);

        static::assertCount(1, $errors);
        $error = $errors[0];
        static::assertInstanceOf(ConfigurationError::class, $error);
        static::assertStringContainsString('test', $error->getMessage());
    }

    private function createValidator(Filesystem $filesystem): ConfigValidator
    {
        return new ConfigValidator(
            new ConfigReader(),
            new StaticSourceResolver(['test' => $filesystem])
        );
    }
}
