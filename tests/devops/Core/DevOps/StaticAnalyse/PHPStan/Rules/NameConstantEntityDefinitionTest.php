<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NameConstantEntityDefinition;

/**
 * @internal
 *
 * @extends  RuleTestCase<NameConstantEntityDefinition>
 */
#[CoversClass(NameConstantEntityDefinition::class)]
class NameConstantEntityDefinitionTest extends RuleTestCase
{
    public function testConstantIsPresentButIsNoEntityDefinition(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NameConstantEntityDefinition/constant-present-but-no-entity-definition.php'],
            []
        );
    }

    public function testConstantIsPresentAndIsEntityDefinition(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NameConstantEntityDefinition/constant-present-in-entity-definition.php'],
            []
        );
    }

    public function testConstantIsPresentAndIsEntityDefinitionButIsNotPublic(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NameConstantEntityDefinition/constant-present-in-entity-definition-but-is-not-public.php'],
            [
                [
                    'EntityDefinitions must declare a public constant named "ENTITY_NAME" which contains the entity name on storage level (e.g. "product").',
                    11,
                ],
            ]
        );
    }

    public function testConstantIsNotPresentAndIsEntityDefinition(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NameConstantEntityDefinition/no-constant-in-entity-definition.php'],
            [
                [
                    'EntityDefinitions must declare a public constant named "ENTITY_NAME" which contains the entity name on storage level (e.g. "product").',
                    11,
                ],
            ]
        );
    }

    public function testConstantIsNotPresentButIsEntityDefinitionInTest(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NameConstantEntityDefinition/no-constant-anonymous-class-in-test.php'],
            []
        );
    }

    public function testConstantIsNotPresentButIsAttributeEntityDefinition(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NameConstantEntityDefinition/no-constant-in-attribute-entity.php'],
            []
        );
    }

    public function testConstantIsNotPresentButIsCustomEntityDefinition(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NameConstantEntityDefinition/no-constant-in-custom-entity.php'],
            []
        );
    }

    public function testConstantIsNotPresentButIsInTestNamespace(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NameConstantEntityDefinition/no-constant-in-test.php'],
            []
        );
    }

    public function testConstantIsNotPresentButIsAbstractEntityDefinition(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NameConstantEntityDefinition/no-constant-in-abstract-entity-definition.php'],
            []
        );
    }

    protected function getRule(): Rule
    {
        return new NameConstantEntityDefinition(
            self::createReflectionProvider(),
        );
    }
}
