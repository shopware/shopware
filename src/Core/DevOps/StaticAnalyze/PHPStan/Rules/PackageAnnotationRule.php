<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
class PackageAnnotationRule implements Rule
{
    private const PRODUCT_AREA_MAPPING = [
        'business-ops' => '/Shopware\\\\.*\\\\(Rule|Flow|ProductStream)\\\\/',
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$area = $this->getProductArea($node)) {
            return [];
        }

        if ($this->hasPackageAnnotation($node, $area)) {
            return [];
        }

        return [sprintf('This class is missing the "@package %s" annotation', $area)];
    }

    private function getProductArea(InClassNode $node): ?string
    {
        $namespace = $node->getClassReflection()->getName();

        foreach (self::PRODUCT_AREA_MAPPING as $area => $regex) {
            if (preg_match($regex, $namespace)) {
                return $area;
            }
        }

        return null;
    }

    private function hasPackageAnnotation(InClassNode $class, string $area): bool
    {
        $doc = $class->getDocComment();

        if ($doc === null) {
            return false;
        }

        return \str_contains($doc->getText(), sprintf('@package %s', $area));
    }
}
