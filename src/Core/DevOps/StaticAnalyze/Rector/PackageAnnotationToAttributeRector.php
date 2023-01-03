<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\Core\Rector\AbstractRector;
use Rector\PhpAttribute\NodeFactory\PhpAttributeGroupFactory;
use Shopware\Core\Framework\Log\Package;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

#[Package('core')]
class PackageAnnotationToAttributeRector extends AbstractRector
{
    /**
     * @readonly
     *
     * @var PhpAttributeGroupFactory
     */
    private $phpAttributeGroupFactory;

    public function __construct(PhpAttributeGroupFactory $phpAttributeGroupFactory)
    {
        $this->phpAttributeGroupFactory = $phpAttributeGroupFactory;
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

        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if (!$phpDocInfo instanceof PhpDocInfo) {
            return null;
        }

        $package = $phpDocInfo->getByName('package');
        if (!$package instanceof PhpDocTagNode) {
            return null;
        }

        $area = $package->value->value;
        if (!$area) {
            return null;
        }
        $array = \explode("\n", $area);

        $area = trim(\array_shift($array));

        $node->attrGroups[] = $this->phpAttributeGroupFactory->createFromClassWithItems(Package::class, [$area]);

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
