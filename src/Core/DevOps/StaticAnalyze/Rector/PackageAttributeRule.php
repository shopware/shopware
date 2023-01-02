<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Core\Rector\AbstractRector;
use Rector\Naming\Naming\UseImportsResolver;
use Rector\Php80\NodeAnalyzer\PhpAttributeAnalyzer;
use Rector\Php80\NodeFactory\AttrGroupsFactory;
use Rector\Php80\NodeManipulator\AttributeGroupNamedArgumentManipulator;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\PhpAttribute\NodeFactory\PhpAttributeGroupFactory;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\PackageAnnotationRule;
use Shopware\Core\Framework\Log\Package;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class PackageAttributeRule extends AbstractRector
{
    /**
     * @var AnnotationToAttribute[]
     */
    private $annotationsToAttributes = [];

    /**
     * @readonly
     *
     * @var PhpAttributeGroupFactory
     */
    private $phpAttributeGroupFactory;

    /**
     * @readonly
     *
     * @var AttrGroupsFactory
     */
    private $attrGroupsFactory;

    /**
     * @readonly
     *
     * @var PhpDocTagRemover
     */
    private $phpDocTagRemover;

    /**
     * @readonly
     *
     * @var AttributeGroupNamedArgumentManipulator
     */
    private $attributeGroupNamedArgumentManipulator;

    /**
     * @readonly
     *
     * @var UseImportsResolver
     */
    private $useImportsResolver;

    /**
     * @readonly
     *
     * @var PhpAttributeAnalyzer
     */
    private $phpAttributeAnalyzer;

    public function __construct(
        PhpAttributeGroupFactory $phpAttributeGroupFactory,
        AttrGroupsFactory $attrGroupsFactory,
        PhpDocTagRemover $phpDocTagRemover,
        AttributeGroupNamedArgumentManipulator $attributeGroupNamedArgumentManipulator,
        UseImportsResolver $useImportsResolver,
        PhpAttributeAnalyzer $phpAttributeAnalyzer
    ) {
        $this->phpAttributeGroupFactory = $phpAttributeGroupFactory;
        $this->attrGroupsFactory = $attrGroupsFactory;
        $this->phpDocTagRemover = $phpDocTagRemover;
        $this->attributeGroupNamedArgumentManipulator = $attributeGroupNamedArgumentManipulator;
        $this->useImportsResolver = $useImportsResolver;
        $this->phpAttributeAnalyzer = $phpAttributeAnalyzer;
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
