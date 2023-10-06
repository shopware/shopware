<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Requirement;

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
 *
 * @covers \Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator
 */
class RequirementsValidatorTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = (new TestBootstrapper())->getProjectDir();
    }

    public function testValidateRequirementsValid(): void
    {
        $path = __DIR__ . '/_fixture/SwagRequirementValidTest';
        $path = str_replace($this->projectDir, '', $path);

        $plugin = $this->createPlugin($path);

        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (\Exception $e) {
            static::fail('This test should not throw an exception, but threw: ' . $e->getMessage());
        }
        static::assertTrue(true);
    }

    public function testValidateRequirementsSubpackageValid(): void
    {
        $path = __DIR__ . '/_fixture/SwagRequirementValidSubpackageTest';
        $path = str_replace($this->projectDir, '', $path);

        $plugin = $this->createPlugin($path);

        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (\Exception $e) {
            static::fail('This test should not throw an exception, but threw: ' . $e->getMessage());
        }
        static::assertTrue(true);
    }

    public function testValidateRequirementsSubpackageWithWildcardValid(): void
    {
        $path = __DIR__ . '/_fixture/SwagRequirementValidSubpackageWildcardTest';
        $path = str_replace($this->projectDir, '', $path);

        $plugin = $this->createPlugin($path);

        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (\Exception $e) {
            static::fail('This test should not throw an exception, but threw: ' . $e->getMessage());
        }
        static::assertTrue(true);
    }

    public function testValidateRequirementsDoNotMatch(): void
    {
        $path = __DIR__ . '/_fixture/SwagRequirementInvalidTest';
        $path = str_replace($this->projectDir, '', $path);

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
        $path = __DIR__ . '/_fixture/SwagRequirementInvalidTest';
        $path = str_replace($this->projectDir, '', $path);

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
        $path = __DIR__ . '/_fixture/SwagRequirementInvalidTest';
        $path = str_replace($this->projectDir, '', $path);

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
        $basePluginPath = __DIR__ . '/_fixture/SwagRequirementValidTest';
        $dependentPluginPath = __DIR__ . '/_fixture/SwagRequirementValidTestExtension';

        $basePlugin = $this->createPlugin(str_replace($this->projectDir, '', $basePluginPath));
        $dependentPlugin = $this->createPlugin(str_replace($this->projectDir, '', $dependentPluginPath));

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

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateConflictsValid(): void
    {
        $path = __DIR__ . '/_fixture/SwagTestValidateConflictsValid';
        $path = str_replace($this->projectDir, '', $path);

        $plugin = $this->createPlugin($path);

        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (\Exception $e) {
            static::fail('This test should not throw an exception, but threw: ' . $e->getMessage());
        }
    }

    public function testValidateConflictsWildcardIncompatibility(): void
    {
        $path = __DIR__ . '/_fixture/SwagTestValidateConflictsWildcardIncompatibility';
        $path = str_replace($this->projectDir, '', $path);

        $plugin = $this->createPlugin($path);

        $this->expectException(RequirementStackException::class);
        $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
    }

    public function testValidateConflictsSpecificMessage(): void
    {
        $path = __DIR__ . '/_fixture/SwagTestValidateConflictsSpecificMessage';
        $path = str_replace($this->projectDir, '', $path);

        $plugin = $this->createPlugin($path);

        $regexTemplate = '#.*"%s" conflicts with plugin/package "%s == 6\.[0-9]+\.[0-9]+\.[0-9]+.*#im';

        static::assertIsString($plugin->getComposerName());
        $this->expectExceptionMessageMatches(sprintf(
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
