<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;

/**
 * @internal
 */
#[CoversClass(Configuration::class)]
class ConfigurationTest extends TestCase
{
    public function testGetConfigTreeBuilder(): void
    {
        $configuration = new Configuration();
        $tree = $configuration->getConfigTreeBuilder();

        static::assertSame('shopware', $tree->buildTree()->getName());
    }

    public function testFeatureToggleConfigTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('feature_toggle', $nodes);
        $node = $nodes['feature_toggle'];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);

        $nodes = $node->getChildNodeDefinitions();

        static::assertArrayHasKey('enable', $nodes);
        $node = $nodes['enable'];
        static::assertInstanceOf(BooleanNodeDefinition::class, $node);
    }

    public function testFeatureConfigTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('feature', $nodes);
        $node = $nodes['feature'];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);

        $nodes = $node->getChildNodeDefinitions();

        static::assertArrayHasKey('flags', $nodes);
        $node = $nodes['flags'];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);

        $node = array_values($node->getChildNodeDefinitions())[0];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);
        $nodes = $node->getChildNodeDefinitions();

        static::assertArrayHasKey('name', $nodes);
        $node = $nodes['name'];
        static::assertInstanceOf(ScalarNodeDefinition::class, $node);

        static::assertArrayHasKey('description', $nodes);
        $node = $nodes['description'];
        static::assertInstanceOf(ScalarNodeDefinition::class, $node);

        static::assertArrayHasKey('major', $nodes);
        $node = $nodes['major'];
        static::assertInstanceOf(BooleanNodeDefinition::class, $node);

        static::assertArrayHasKey('toggleable', $nodes);
        $node = $nodes['toggleable'];
        static::assertInstanceOf(BooleanNodeDefinition::class, $node);

        static::assertArrayHasKey('default', $nodes);
        $node = $nodes['default'];
        static::assertInstanceOf(BooleanNodeDefinition::class, $node);
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

    public function testSearchTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('search', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $searchNode = $nodes['search']);

        $nodes = $searchNode->getChildNodeDefinitions();

        static::assertArrayHasKey('preserved_chars', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['preserved_chars']);
    }

    public function testSystemConfigTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('system_config', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['system_config']);

        $nodes = $nodes['system_config']->getChildNodeDefinitions();

        static::assertArrayHasKey('default', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['default']);
    }
}
