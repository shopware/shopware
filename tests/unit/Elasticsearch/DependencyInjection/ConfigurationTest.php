<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\DependencyInjection\Configuration
 */
class ConfigurationTest extends TestCase
{
    public function testConfigTree(): void
    {
        $configuration = new Configuration();
        $tree = $configuration->getConfigTreeBuilder();
        static::assertInstanceOf(TreeBuilder::class, $tree);

        static::assertSame('elasticsearch', $tree->buildTree()->getName());
    }
}
