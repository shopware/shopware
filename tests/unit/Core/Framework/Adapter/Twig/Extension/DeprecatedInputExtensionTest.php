<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\DeprecatedInputExtension;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\DeprecatedInputNodeVisitor;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\DeprecatedInputTokenParser;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeprecatedInputExtension::class)]
class DeprecatedInputExtensionTest extends TestCase
{
    public function testRegistersParserAndVisitor(): void
    {
        $extension = new DeprecatedInputExtension();

        static::assertContainsOnlyInstancesOf(DeprecatedInputTokenParser::class, $extension->getTokenParsers());
        static::assertContainsOnlyInstancesOf(DeprecatedInputNodeVisitor::class, $extension->getNodeVisitors());
    }
}
