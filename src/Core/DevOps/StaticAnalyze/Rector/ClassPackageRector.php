<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use Rector\Core\Rector\AbstractRector;
use Rector\PhpAttribute\NodeFactory\PhpAttributeGroupFactory;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\PackageAnnotationRule;
use Shopware\Core\Framework\Log\Package;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

#[Package('core')]
class ClassPackageRector extends AbstractRector
{
    private string $template = <<<'PHP'
/**
 * @package %s
 */
PHP;

    public function __construct(private readonly PhpAttributeGroupFactory $phpAttributeGroupFactory)
    {
    }

    public function getNodeTypes(): array
    {
        return [ClassLike::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof ClassLike) {
            return null;
        }

        if ($this->isTestClass($node)) {
            return null;
        }

        $area = $this->getArea($node);

        if ($area === null) {
            return null;
        }

        if ($this->hasPackageAnnotation($node)) {
            return null;
        }

        $node->attrGroups[] = $this->phpAttributeGroupFactory->createFromClassWithItems(Package::class, [$area]);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Adds a #[Package] attribute to all php classes corresponding to the area mapping.',
            [
                new CodeSample(
                    // code before
                    '
class Foo{}',

                    // code after
                    '
#[Package(\'core\')]
class Foo{}'
                ),
            ]
        );
    }

    private function getArea(ClassLike $node): ?string
    {
        try {
            $namespace = $node->namespacedName->toString();
        } catch (\Throwable $e) {
            return null;
        }

        foreach (PackageAnnotationRule::PRODUCT_AREA_MAPPING as $area => $regexes) {
            foreach ($regexes as $regex) {
                if (preg_match($regex, $namespace)) {
                    return $area;
                }
            }
        }

        return null;
    }

    private function hasPackageAnnotation(ClassLike $class): bool
    {
        foreach ($class->attrGroups as $group) {
            $attribute = $group->attrs[0];

            /** @var Node\Name\FullyQualified $name */
            $name = $attribute->name;

            if ($name->toString() === Package::class) {
                return true;
            }
        }

        return false;
    }

    private function isTestClass(ClassLike $node): bool
    {
        try {
            $namespace = $node->namespacedName->toString();
        } catch (\Throwable $e) {
            return true;
        }

        return \str_contains($namespace, '\\Tests\\') || \str_contains($namespace, '\\Test\\');
    }
}
