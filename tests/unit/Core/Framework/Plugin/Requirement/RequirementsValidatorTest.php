<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Requirement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\TestBootstrapper;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 */
#[CoversClass(RequirementsValidator::class)]
class RequirementsValidatorTest extends TestCase
{
    private string $projectDir;

    private string $fixturePath;

    protected function setUp(): void
    {
        $this->projectDir = (new TestBootstrapper())->getProjectDir();
        $this->fixturePath = __DIR__ . '/../../../../../../src/Core/Framework/Test/Plugin/Requirement/_fixture/';
    }

    public function testValidateRequirementsValid(): void
    {
        $path = str_replace($this->projectDir, '', $this->fixturePath . 'SwagRequirementValidTest');

        $plugin = $this->createPlugin($path);

        $exception = null;
        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (RequirementStackException $exception) {
        }
        static::assertNull($exception);
    }

    public function testValidateRequirementsSubpackageValid(): void
    {
        $path = str_replace($this->projectDir, '', $this->fixturePath . 'SwagRequirementValidSubpackageTest');

        $plugin = $this->createPlugin($path);

        $exception = null;
        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (RequirementStackException $exception) {
        }
        static::assertNull($exception);
    }

    public function testValidateRequirementsSubpackageWithWildcardValid(): void
    {
        $path = str_replace($this->projectDir, '', $this->fixturePath . 'SwagRequirementValidSubpackageWildcardTest');

        $plugin = $this->createPlugin($path);

        $exception = null;
        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (RequirementStackException $exception) {
        }
        static::assertNull($exception);
    }

    public function testValidateRequirementsDoNotMatch(): void
    {
        $path = str_replace($this->projectDir, '', $this->fixturePath . 'SwagRequirementInvalidTest');

        $plugin = $this->createPlugin($path);

        $exception = null;

        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (RequirementStackException $exception) {
        }

        $packages = [];
        static::assertInstanceOf(RequirementStackException::class, $exception);
        foreach ($exception->getRequirements() as $requirement) {
            $packages[] = $requirement->getParameters()['requirement'];
        }

        static::assertContains('shopware/platform', $packages);
        static::assertContains('test/not-installed', $packages);
    }

    public function testValidateRequirementsMissing(): void
    {
        $path = str_replace($this->projectDir, '', $this->fixturePath . 'SwagRequirementInvalidTest');

        $plugin = $this->createPlugin($path);

        $exception = null;

        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (RequirementStackException $exception) {
        }

        $messages = [];
        static::assertInstanceOf(RequirementStackException::class, $exception);
        foreach ($exception->getRequirements() as $requirement) {
            $messages[] = $requirement->getMessage();
        }

        static::assertContains(
            'Required plugin/package "test/not-installed ~2" is missing or not installed and activated',
            $messages
        );
    }

    public function testDoesNotValidateIfPluginIsManagedByComposer(): void
    {
        $path = str_replace($this->projectDir, '', $this->fixturePath . 'SwagRequirementInvalidTest');

        $plugin = $this->createPlugin($path);
        $plugin->setManagedByComposer(true);

        $exception = null;

        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (RequirementStackException $exception) {
        }

        static::assertNull($exception);
    }

    public function testResolveActiveDependants(): void
    {
        $basePlugin = $this->createPlugin(str_replace($this->projectDir, '', $this->fixturePath . 'SwagRequirementValidTest'));
        $dependentPlugin = $this->createPlugin(str_replace($this->projectDir, '', $this->fixturePath . 'SwagRequirementValidTestExtension'));

        $basePlugin->setActive(true);
        $dependentPlugin->setActive(true);
        $basePlugin->setComposerName('swag/requirement-valid-test');
        $dependentPlugin->setComposerName('swag/requirement-valid-test-extension');

        $dependants = $this->createValidator()->resolveActiveDependants($dependentPlugin, [$basePlugin, $dependentPlugin]);

        static::assertEmpty($dependants);

        $dependants = $this->createValidator()->resolveActiveDependants($basePlugin, [$basePlugin, $dependentPlugin]);

        static::assertCount(1, $dependants);

        $dependentPlugin->setActive(false);

        $dependants = $this->createValidator()->resolveActiveDependants($basePlugin, [$basePlugin, $dependentPlugin]);

        static::assertEmpty($dependants);
    }

    public function testValidateConflictsValid(): void
    {
        $path = str_replace($this->projectDir, '', $this->fixturePath . 'SwagTestValidateConflictsValid');

        $plugin = $this->createPlugin($path);

        $exception = null;
        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (\Exception $exception) {
        }

        static::assertNull($exception);
    }

    public function testValidateConflictsWildcardIncompatibility(): void
    {
        $path = str_replace($this->projectDir, '', $this->fixturePath . 'SwagTestValidateConflictsWildcardIncompatibility');

        $plugin = $this->createPlugin($path);

        $this->expectException(RequirementStackException::class);
        $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
    }

    public function testValidateConflictsSpecificMessage(): void
    {
        $path = str_replace($this->projectDir, '', $this->fixturePath . 'SwagTestValidateConflictsSpecificMessage');

        $plugin = $this->createPlugin($path);

        $regexTemplate = '#.*"%s" conflicts with plugin/package "%s == 6\.[0-9]+\.[0-9]+\.[0-9]+.*#im';

        static::assertIsString($plugin->getComposerName());
        $this->expectExceptionMessageMatches(\sprintf(
            $regexTemplate,
            preg_quote($plugin->getComposerName(), '#'),
            preg_quote('shopware/core', '#')
        ));
        $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
    }

    private function createValidator(): RequirementsValidator
    {
        $pluginRepo = $this->createMock(EntityRepository::class);
        $pluginRepo->method('search')->willReturn(new EntitySearchResult(
            'plugin',
            0,
            new PluginCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return new RequirementsValidator(
            $pluginRepo,
            $this->projectDir
        );
    }

    private function createPlugin(string $path): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setPath($path);
        $plugin->setManagedByComposer(false);
        $plugin->setVersion('1.0.0');
        $plugin->setComposerName('swag/' . (new CamelCaseToSnakeCaseNameConverter())->normalize(basename($path)));

        return $plugin;
    }
}
