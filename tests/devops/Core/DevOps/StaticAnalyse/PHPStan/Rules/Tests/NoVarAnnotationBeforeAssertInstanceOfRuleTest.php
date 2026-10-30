<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\FileTypeMapper;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoVarAnnotationBeforeAssertInstanceOfRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoVarAnnotationBeforeAssertInstanceOfRule>
 */
#[Package('framework')]
class NoVarAnnotationBeforeAssertInstanceOfRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/NoVarAnnotationBeforeAssertInstanceOf/no-var-annotation-before-assert-instance-of-cases.php'],
            [
                [
                    'Drop the @var annotation: the assertInstanceOf() below already narrows $product to ProductEntity.',
                    17,
                ],
                [
                    'Drop the @var annotation: the assertInstanceOf() below already narrows $product to ProductEntity.',
                    25,
                ],
                [
                    'Drop the @var annotation: the assertInstanceOf() below already narrows $product to ProductEntity.',
                    35,
                ],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new NoVarAnnotationBeforeAssertInstanceOfRule(self::getContainer()->getByType(FileTypeMapper::class));
    }
}
