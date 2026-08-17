<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTreeConstraints::class)]
class StoredTreeConstraintsTest extends TestCase
{
    #[TestDox('reports no violation for a well-formed forest carrying every element field')]
    public function testValidatesAWellFormedForest(): void
    {
        $forest = [
            [
                'id' => 'root-1',
                'component' => 'core:section',
                'properties' => ['title' => 'Hello'],
                'dataRequirements' => [
                    'products' => ['key' => 'products', 'source' => 'entity', 'config' => []],
                ],
                'slots' => [
                    'main' => [
                        ['id' => 'child-1', 'component' => 'core:text', 'properties' => []],
                    ],
                ],
                'providesContext' => [
                    'product' => [
                        'type' => 'single',
                        'distribution' => 'keyed',
                        'keyProperty' => 'sku',
                        'consumerAlias' => null,
                    ],
                ],
                'acceptsContext' => [
                    'items' => ['type' => 'collection', 'required' => true],
                ],
                'style' => ['col-span' => ['md' => 6]],
                'attributedSpecifications' => ['image' => 'core:product-image'],
            ],
        ];

        static::assertCount(0, $this->validate($forest));
    }

    #[TestDox('reports a violation for a style option the registry does not know')]
    public function testRejectsAnUnknownStyleOption(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:text',
                'properties' => [],
                'style' => ['removed-plugin-option' => ['md' => 2]],
            ],
        ]);

        static::assertCount(1, $violations);
        static::assertSame('[0][style][removed-plugin-option]', $violations->get(0)->getPropertyPath());
    }

    #[TestDox('reports no violation for a style option the registry knows')]
    public function testAcceptsAKnownStyleOption(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:text',
                'properties' => [],
                'style' => ['col-span' => ['md' => 6]],
            ],
        ]);

        static::assertCount(0, $violations);
    }

    #[TestDox('reaches a nested slot child and reports its violation at a path identifying that child')]
    public function testReportsAViolationOnANestedSlotChild(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:section',
                'properties' => [],
                'slots' => [
                    'main' => [
                        ['id' => 'child-1', 'component' => 'core:section', 'properties' => [], 'slots' => [
                            'inner' => [
                                ['id' => '', 'component' => 'core:text', 'properties' => []],
                            ],
                        ]],
                    ],
                ],
            ],
        ]);

        static::assertCount(1, $violations);
        static::assertSame(
            '[0][slots][main][0].[slots][inner][0].[id]',
            $violations->get(0)->getPropertyPath()
        );
    }

    #[TestDox('reports a violation for a forest whose root keys are not a sequential list')]
    public function testRejectsANonSequentialForest(): void
    {
        $validator = Validation::createValidatorBuilder()->getValidator();

        $forest = [
            0 => ['id' => 'root-1', 'component' => 'core:text', 'properties' => []],
            2 => ['id' => 'root-2', 'component' => 'core:text', 'properties' => []],
        ];

        $violations = $validator->validate($forest, $this->constraints()->build());

        static::assertGreaterThanOrEqual(1, $violations->count());
    }

    #[TestDox('reports a violation for a slot whose children keys are not a sequential list')]
    public function testRejectsANonSequentialSlotChildrenArray(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:section',
                'properties' => [],
                'slots' => [
                    'main' => [
                        0 => ['id' => 'child-1', 'component' => 'core:text', 'properties' => []],
                        2 => ['id' => 'child-2', 'component' => 'core:text', 'properties' => []],
                    ],
                ],
            ],
        ]);

        static::assertGreaterThanOrEqual(1, $violations->count());
    }

    #[TestDox('reports a violation naming the offending key for a numeric key in a wiring map')]
    public function testRejectsANumericWiringMapKey(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:text',
                'properties' => [12 => 'x'],
            ],
        ]);

        static::assertCount(1, $violations);
        static::assertSame(
            'This map must be keyed by strings, 12 is not a string key.',
            (string) $violations->get(0)->getMessage()
        );
    }

    #[TestDox('reports a violation for a consumer alias declared without redistribution')]
    public function testRejectsAConsumerAliasWithoutRedistribute(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:text',
                'properties' => [],
                'acceptsContext' => [
                    'items' => ['type' => 'single', 'required' => true, 'consumerAlias' => 'inner'],
                ],
            ],
        ]);

        static::assertCount(1, $violations);
        static::assertSame(
            'This value requires "redistribute" to be true.',
            (string) $violations->get(0)->getMessage()
        );
    }

    #[TestDox('reports a violation for a property alias carrying dot notation')]
    public function testRejectsAPropertyAliasWithDotNotation(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:text',
                'properties' => [],
                'acceptsContext' => [
                    'items' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product.name'],
                ],
            ],
        ]);

        static::assertCount(1, $violations);
        static::assertSame(
            'This value should be a simple property name without dot notation.',
            (string) $violations->get(0)->getMessage()
        );
    }

    /**
     * @param list<array<array-key, mixed>> $forest
     */
    private function validate(array $forest): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()->getValidator();

        return $validator->validate($forest, $this->constraints()->build());
    }

    private function constraints(): StoredTreeConstraints
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'col-span' => new StyleOptionSpecification(
                'col-span',
                new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null),
                true,
                null,
                'core'
            ),
        ]);

        return new StoredTreeConstraints($registry, new StyleOptionConstraintDeriver());
    }
}
