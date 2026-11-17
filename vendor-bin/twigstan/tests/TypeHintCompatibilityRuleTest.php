<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<TypeHintCompatibilityRule>
 */
#[Package('framework')]
#[CoversClass(TypeHintCompatibilityRule::class)]
class TypeHintCompatibilityRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/fixtures/type-hint-compatibility.neon'];
    }

    public function testChecksInferredTypesBeforeDeclarationsReplaceThem(): void
    {
        $this->analyse([__DIR__ . '/fixtures/type-hint-compatibility.php'], [
            ['Type declaration for "other" expects DateTime|DateTimeImmutable, but inferred type is stdClass.', 20],
            ['Type declaration for "partial" expects DateTime|DateTimeImmutable, but inferred type is DateTimeImmutable|stdClass.', 21],
            ['Type declaration for "nullable" expects DateTimeInterface, but inferred type is DateTimeImmutable|null.', 23],
            ['Type declaration for "depth" expects int, but inferred type is int|string.', 24],
            ['Type declaration for "data" expects DateTime|DateTimeImmutable, but inferred type is stdClass.', 30],
            ['Type declaration for "data" expects stdClass, but inferred type is DateTime|DateTimeImmutable.', 31],
            ['Type declaration requires variable "data" to be defined.', 37],
            ['Type declaration requires variable "data" to be defined.', 45],
            ['Type declaration for "data" expects int, but inferred type is string.', 60],
        ]);
    }

    protected function getRule(): Rule
    {
        return new TypeHintCompatibilityRule(self::getContainer()->getByType(TypeStringResolver::class));
    }
}
