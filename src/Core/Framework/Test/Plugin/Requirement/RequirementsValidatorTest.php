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
        static::markTestSkipped('NEXT-4442 - Test does not work if a different development version is checked out');
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
        static::markTestSkipped('NEXT-4442 - Test does not work if a different development version is checked out');
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
        static::markTestSkipped('NEXT-4442 - Test does not work if a different development version is checked out');
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
            'Required plugin/package "shopware/platform ^12.34" does not match installed version 9999999-dev.',
            $messages
        );
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

    private function createValidator(): RequirementsValidator
    {
        return new RequirementsValidator(
            $this->getContainer()->get('plugin.repository'),
            $this->getContainer()->getParameter('kernel.project_dir')
        );
    }

    private function createPlugin($path): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setPath($path);
        $plugin->setManagedByComposer(false);

        return $plugin;
    }
}
