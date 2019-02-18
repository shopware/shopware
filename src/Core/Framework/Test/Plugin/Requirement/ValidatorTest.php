<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Requirement;

use Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class ValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    public function testValidateRequirementsValid(): void
    {
        require_once __DIR__ . '/_fixture/SwagRequirementValidTest/SwagRequirementValidTest.php';
        $pluginBaseClass = new \SwagRequirementValidTest\SwagRequirementValidTest();

        try {
            $this->createValidator()->validateRequirements($pluginBaseClass, Context::createDefaultContext(), 'test');
        } catch (Exception $e) {
            static::fail('This test should not throw an exception');
        }
        static::assertTrue(true);
    }

    public function testValidateRequirementsDoNotMatch(): void
    {
        require_once __DIR__ . '/_fixture/SwagRequirementInvalidTest/SwagRequirementInvalidTest.php';
        $pluginBaseClass = new \SwagRequirementInvalidTest\SwagRequirementInvalidTest();

        $this->expectException(RequirementStackException::class);
        $this->expectExceptionMessage('Required plugin/package "shopware/platform ^12.34" does not match installed version');

        $this->createValidator()->validateRequirements($pluginBaseClass, Context::createDefaultContext(), 'test');
    }

    public function testValidateRequirementsMissing(): void
    {
        require_once __DIR__ . '/_fixture/SwagRequirementInvalidTest/SwagRequirementInvalidTest.php';
        $pluginBaseClass = new \SwagRequirementInvalidTest\SwagRequirementInvalidTest();

        $this->expectException(RequirementStackException::class);
        $this->expectExceptionMessage('Required plugin/package "test/not-installed ~2" is missing');

        $this->createValidator()->validateRequirements($pluginBaseClass, Context::createDefaultContext(), 'test');
    }

    private function createValidator(): RequirementsValidator
    {
        return new RequirementsValidator(
            $this->getContainer()->get('plugin.repository'),
            $this->getContainer()->getParameter('kernel.project_dir')
        );
    }
}
