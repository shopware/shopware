<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Requirement;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class RequirementsValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    public function testValidateRequirementsValid(): void
    {
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $path = __DIR__ . '/_fixture/SwagRequirementValidTest';
        $path = str_replace($projectDir, '', $path);

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
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $path = __DIR__ . '/_fixture/SwagRequirementValidSubpackageTest';
        $path = str_replace($projectDir, '', $path);

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
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $path = __DIR__ . '/_fixture/SwagRequirementValidSubpackageWildcardTest';
        $path = str_replace($projectDir, '', $path);

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
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $path = __DIR__ . '/_fixture/SwagRequirementInvalidTest';
        $path = str_replace($projectDir, '', $path);

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
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $path = __DIR__ . '/_fixture/SwagRequirementInvalidTest';
        $path = str_replace($projectDir, '', $path);

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

    public function testResolveActiveDependants(): void
    {
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        $basePluginPath = __DIR__ . '/_fixture/SwagRequirementValidTest';
        $dependentPluginPath = __DIR__ . '/_fixture/SwagRequirementValidTestExtension';

        $basePlugin = $this->createPlugin(str_replace($projectDir, '', $basePluginPath));
        $dependentPlugin = $this->createPlugin(str_replace($projectDir, '', $dependentPluginPath));

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

    private function createValidator(): RequirementsValidator
    {
        return new RequirementsValidator(
            $this->getContainer()->get('plugin.repository'),
            $this->getContainer()->getParameter('kernel.project_dir')
        );
    }

    private function createPlugin(string $path): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setPath($path);
        $plugin->setManagedByComposer(false);

        return $plugin;
    }
}
