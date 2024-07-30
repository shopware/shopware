<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('core')]
class DALFieldsMustBeRegisteredWithSchemaBuilder implements Rule
{
    use InTestClassTrait;

    /**
     * @var array<class-string, class-string>|null
     */
    private ?array $mappings = null;

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $ref = $scope->getClassReflection();

        if ($ref === null) {
            return [];
        }

        if (!\str_starts_with($ref->getName(), 'Shopware\\Core\\')) {
            return [];
        }

        if (!$ref->isSubclassOf(Field::class)) {
            return [];
        }

        if ($this->isInTestClass($scope)) {
            // if in a test namespace, don't care
            return [];
        }

        if ($ref->is(AssociationField::class) || $ref->is(TranslatedField::class)) {
            return [];
        }

        if (!$this->isRegisteredWithSchemaBuilder($ref->getName())) {
            return [RuleErrorBuilder::message(
                \sprintf('Field %s must be registered with %s', $ref->getName(), SchemaBuilder::class)
            )->build()];
        }

        return [];
    }

    public function isRegisteredWithSchemaBuilder(string $class): bool
    {
        if (!$this->mappings) {
            $this->mappings = $this->getSchemaBuilderMappings();
        }

        return isset($this->mappings[$class]);
    }

    /**
     * @return array<class-string, class-string>
     */
    public function getSchemaBuilderMappings(): array
    {
        $reflectionProperty = $this->reflectionProvider
            ->getClass(SchemaBuilder::class)
            ->getNativeReflection()
            ->getProperty('fieldMapping');

        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue();
    }
}
