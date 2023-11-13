<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DependencyInjection\Configuration
 */
class ConfigurationTest extends TestCase
{
    public function testGetConfigTreeBuilder(): void
    {
        $configuration = new Configuration();
        $tree = $configuration->getConfigTreeBuilder();

        static::assertInstanceOf(TreeBuilder::class, $tree);
        static::assertSame('shopware', $tree->buildTree()->getName());
    }

    public function testHtmlSanitizerConfigTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('html_sanitizer', $nodes);
        $node = $nodes['html_sanitizer'];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);

        $nodes = $node->getChildNodeDefinitions();

        static::assertInstanceOf(VariableNodeDefinition::class, $nodes['cache_dir']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['sets']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['fields']);

        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes = $nodes['sets']->getChildNodeDefinitions()['']);
        $setsNodes = $setsNodes->getChildNodeDefinitions();
        static::assertInstanceOf(ScalarNodeDefinition::class, $setsNodes['name']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes['tags']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes['attributes']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes['options']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes['custom_attributes']);

        static::assertInstanceOf(ArrayNodeDefinition::class, $customAttributeNodes = $setsNodes['custom_attributes']->getChildNodeDefinitions()['']);
        $customAttributeNodes = $customAttributeNodes->getChildNodeDefinitions();
        static::assertInstanceOf(ArrayNodeDefinition::class, $customAttributeNodes['tags']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $customAttributeNodes['attributes']);

        static::assertInstanceOf(ArrayNodeDefinition::class, $optionsNodes = $setsNodes['options']->getChildNodeDefinitions()['']);
        $optionsNodes = $optionsNodes->getChildNodeDefinitions();
        static::assertInstanceOf(ScalarNodeDefinition::class, $optionsNodes['key']);
        static::assertInstanceOf(ScalarNodeDefinition::class, $optionsNodes['value']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $optionsNodes['values']);

        static::assertInstanceOf(ArrayNodeDefinition::class, $fieldsNodes = $nodes['fields']->getChildNodeDefinitions()['']);
        $fieldsNodes = $fieldsNodes->getChildNodeDefinitions();
        static::assertInstanceOf(ScalarNodeDefinition::class, $fieldsNodes['name']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $fieldsNodes['sets']);
    }
}
