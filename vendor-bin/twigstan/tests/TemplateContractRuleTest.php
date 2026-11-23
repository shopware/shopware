<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @extends RuleTestCase<TemplateContractRule>
 */
#[Package('framework')]
#[CoversClass(TemplateContractRule::class)]
class TemplateContractRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/fixtures/template-contracts.neon'];
    }

    public static function createEnvironment(): Environment
    {
        return new Environment(new FilesystemLoader(__DIR__ . '/fixtures'));
    }

    public function testReportsInvalidCallersAtTheirOwnIncludeLines(): void
    {
        $this->analyse([__DIR__ . '/fixtures/template-contract-calls.php'], [
            ['Template "template-contract.twig" expects "name" to be string, 42 given.', 11],
            ['Template "template-contract.twig" requires variable "name" to be defined.', 12],
            ['Template "template-contract.twig" requires variable "name" to be defined.', 13],
            ['Template "template-contract.twig" expects "size" to be string|null, 42 given.', 16],
        ]);
    }

    protected function getRule(): Rule
    {
        return new TemplateContractRule(
            self::getContainer()->getByType(TemplateContracts::class),
        );
    }
}
