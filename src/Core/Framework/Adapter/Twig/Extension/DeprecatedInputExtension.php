<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\DeprecatedInputNodeVisitor;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\DeprecatedInputTokenParser;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedInputExtension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [new DeprecatedInputTokenParser()];
    }

    public function getNodeVisitors(): array
    {
        return [new DeprecatedInputNodeVisitor()];
    }
}
