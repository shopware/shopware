<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Core\Rector\AbstractRector;
use Rector\PhpAttribute\NodeFactory\PhpAttributeGroupFactory;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\PackageAnnotationRule;
use Shopware\Core\Framework\Log\Package;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @package core
 */
class ClassPackageRector extends AbstractRector
{
    public function __construct(private PhpAttributeGroupFactory $phpAttributeGroupFactory, private PhpDocTagRemover $phpDocTagRemover)
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

        /** @var PhpDocInfo $phpDocInfo */
        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);

        /** @var PhpDocTagNode $packageAnnotation */
        $packageAnnotation = $phpDocInfo->getByName('Since');

        if ($packageAnnotation === null) {
            return null;
        }

        $this->phpDocTagRemover->removeByName($phpDocInfo, 'Since');

//        $node->attrGroups[] = $this->phpAttributeGroupFactory->createFromClassWithItems(Package::class, [$packageAnnotation->value->value]);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Adds a @package annotation to all php classes corresponding to the area mapping.',
            [
                new CodeSample(
                    // code before
'
/**
 * some docs
 */
class Foo{}',

                    // code after
'
/**
 * @package area
 * some docs
 */
class Foo{}'
                ),
            ]
        );
    }

    private function getArea(ClassLike $node): ?string
    {
        try {
            $namespace = $node->namespacedName->toString();
        } catch (\Throwable) {
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

    private function isTestClass(ClassLike $node): bool
    {
        try {
            $namespace = $node->namespacedName->toString();
        } catch (\Throwable) {
            return true;
        }

        return \str_contains($namespace, '\\Tests\\') || \str_contains($namespace, '\\Test\\');
    }
}
