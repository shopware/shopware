<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\PropertyTypeConformanceValidator;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The descriptor fixture {@see StoredTreeConstraintsTest} and {@see StoredTreeWiringConstraintsTest} share:
 * one style-option registry, one element-type registry, and the validator built around them. Both files
 * validate a forest against {@see StoredTreeConstraints::build()}, which is the only entry point either side
 * of the split has.
 *
 * @internal
 */
#[Package('framework')]
abstract class StoredTreeConstraintsTestCase extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected function element(array $overrides): array
    {
        return ['id' => 'root-1', 'component' => 'core:text', 'properties' => [], ...$overrides];
    }

    /**
     * @param list<array<array-key, mixed>> $forest
     */
    protected function validate(array $forest): ConstraintViolationListInterface
    {
        return $this->validator()->validate($forest, $this->constraints()->build());
    }

    /**
     * The descriptor attaches a constraint whose validator carries the element-type registry, so the default
     * factory (which builds every validator with `new`) cannot supply it.
     */
    protected function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                PropertyTypeConformanceValidator::class => new PropertyTypeConformanceValidator($this->typeRegistry()),
            ]))
            ->getValidator();
    }

    protected function constraints(): StoredTreeConstraints
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'col-span' => new StyleOptionSpecification('col-span', new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null), true, null, 'core'),
            'align-self' => new StyleOptionSpecification('align-self', new StyleOptionValueType('string', ['auto', 'start', 'center'], null, null, 'auto'), true, null, 'core'),
            'margin' => new StyleOptionSpecification('margin', new StyleOptionValueType('string', null, null, 8, null), true, null, 'core'),
            'display' => new StyleOptionSpecification('display', new StyleOptionValueType('boolean', null, null, null, null), true, null, 'core'),
            'z-index' => new StyleOptionSpecification('z-index', new StyleOptionValueType('integer', null, null, null, null), false, null, 'core'),
            'flat-label' => new StyleOptionSpecification('flat-label', new StyleOptionValueType('string', null, null, 8, null), false, null, 'core'),
        ]);

        return new StoredTreeConstraints($registry, new StyleOptionConstraintDeriver());
    }

    /**
     * Knows one type, `core:text`, declaring one string property. Every other payload in these files names a
     * component the registry does not know or a key that type does not declare, so the property-type rule is
     * inert for them and each test still pins the part of the descriptor it was written for.
     */
    private function typeRegistry(): AbstractContentSystemElementTypeRegistry
    {
        $specs = ['core:text' => new ContentSystemElementTypeSpecification(
            'core:text',
            'Text',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            ['headline' => new PropertySpecification('headline', new PropertyType('string', false, null, null), false, '', '', null)],
            [],
        )];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }
}
