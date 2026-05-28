<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class BrandedStringTypeNodeResolverExtension implements TypeNodeResolverExtension
{
    public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
    {
        if (!$typeNode instanceof IdentifierTypeNode) {
            return null;
        }

        return match ($typeNode->name) {
            'ContextToken' => new BrandedStringType('ContextToken', TrinaryLogic::createYes(), TrinaryLogic::createYes()),
            'CartToken' => new BrandedStringType('CartToken', TrinaryLogic::createYes(), TrinaryLogic::createYes()),
            default => null,
        };
    }
}
