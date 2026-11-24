<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class FeatureGuardedDeprecationExtension implements IgnoreErrorExtension
{
    /**
     * @var array<string, array<Node\Stmt>>
     */
    private array $files = [];

    public function __construct(private readonly Parser $parser)
    {
    }

    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if (!\in_array($error->getIdentifier(), [
            'method.deprecated',
            'method.deprecatedClass',
            'method.deprecatedInterface',
            'staticMethod.deprecated',
            'staticMethod.deprecatedClass',
            'new.deprecatedClass',
            'classConstant.deprecated',
            'classConstant.deprecatedClass',
            'classConstant.deprecatedInterface',
            'property.deprecated',
            'property.deprecatedClass',
            'catch.deprecatedClass',
            'instanceof.deprecatedClass',
        ], true)
            || preg_match('/\btag:(v\d+\.\d+\.\d+(?:\.\d+)?)\b/', $error->getMessage(), $match) !== 1
        ) {
            return false;
        }
        $version = $match[1] . (substr_count($match[1], '.') === 2 ? '.0' : '');
        if (!Feature::isMajorVersionFlag($version)) {
            return false;
        }
        $file = $scope->getFile();
        $nodes = $this->files[$file] ??= $this->parser->parseFile($file);

        return (new FeatureFlagGuard($node, Feature::normalizeName($version)))->matches($nodes);
    }
}
