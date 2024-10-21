<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\DependencyInjection\Configuration;

/**
 * @internal
 */
#[CoversClass(Configuration::class)]
class ConfigurationTest extends TestCase
{
    public function testConfigTree(): void
    {
        $configuration = new Configuration();
        $tree = $configuration->getConfigTreeBuilder();

        static::assertSame('elasticsearch', $tree->buildTree()->getName());
    }
}
