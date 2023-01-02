<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Rector;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use Rector\Core\Rector\AbstractRector;
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

        $doc = $node->getDocComment();
        if ($doc === null) {
            $node->setDocComment(new Doc(\sprintf($this->template, $area)));

            return $node;
        }

        $text = $node->getDocComment()->getText();
        if (\str_contains($text, '@package')) {
            return null;
        }

        $text = \str_replace('*/', \sprintf(' * @package %s', $area) . \PHP_EOL . ' */', $text);

        $node->setDocComment(new Doc($text));

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
