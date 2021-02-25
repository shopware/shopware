<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Requirement;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class ConflictingPackageTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateConflictsValid(): void
    {
        $plugin = $this->createTestPlugin(__FUNCTION__);

        try {
            $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
        } catch (\Exception $e) {
            static::fail('This test should not throw an exception, but threw: ' . $e->getMessage());
        }
    }

    public function testValidateConflictsWildcardIncompatibility(): void
    {
        $plugin = $this->createTestPlugin(__FUNCTION__);

        $this->expectException(RequirementStackException::class);
        $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
    }

    public function testValidateConflictsSpecificMessage(): void
    {
        $plugin = $this->createTestPlugin(__FUNCTION__);

        $regexTemplate = '#.*"%s" conflicts with plugin/package "%s == 6\.[0-9]+\.[0-9]+\.[0-9]+.*#im';

        $this->expectExceptionMessageMatches(sprintf(
            $regexTemplate,
            preg_quote($plugin->getComposerName(), '#'),
            preg_quote('shopware/core', '#')
        ));
        $this->createValidator()->validateRequirements($plugin, Context::createDefaultContext(), 'test');
    }

    private function createValidator(): RequirementsValidator
    {
        return new RequirementsValidator(
            $this->getContainer()->get('plugin.repository'),
            $this->getContainer()->getParameter('kernel.project_dir')
        );
    }

    private function createTestPlugin(string $pluginName): PluginEntity
    {
        $kebab = 'swag/' . strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^-])([A-Z][a-z])/'], '$1-$2', $pluginName));
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $path = __DIR__ . '/_fixture/Swag' . ucfirst($pluginName);
        $path = str_replace($projectDir, '', $path);

        $plugin = new PluginEntity();
        $plugin->setPath($path);
        $plugin->setManagedByComposer(false);
        $plugin->setComposerName($kebab);
        $plugin->setVersion('1.0.0');

        return $plugin;
    }
}
