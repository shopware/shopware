<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
class MessageHandlerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('messenger.message_handler') as $id => $tags) {
            $definition = $container->getDefinition($id);
            $tags = $definition->getTags();

            $class = $definition->getClass() ?? $id;

            if (empty($class) || !class_exists($class)) {
                continue;
            }

            $attributes = (new \ReflectionClass($class))->getAttributes(AsMessageHandler::class);

            if (empty($attributes)) {
                continue;
            }

            $tagAttributes = $tags['messenger.message_handler'][0];

            foreach ($attributes as $attribute) {
                $tagAttributes = array_merge($attribute->getArguments(), $tagAttributes);
            }

            $tags['messenger.message_handler'] = [$tagAttributes];

            $definition->setTags($tags);
        }


        if ($container->hasDefinition('Shopware\Core\Framework\MessageQueue\Stats\StatsService')) {
            $receiverMapping = [];
            foreach ($container->findTaggedServiceIds('messenger.receiver') as $id => $tags) {
                $receiverMapping[$id] = $id;
                foreach ($tags as $tag) {
                    if (isset($tag['alias'])) {
                        $receiverMapping[$tag['alias']] = $receiverMapping[$id];
                    }
                }
            }

            $receiverNames = [];
            foreach ($receiverMapping as $name => $id) {
                $receiverNames[$id] = $name;
            }

            $container->getDefinition('Shopware\Core\Framework\MessageQueue\Stats\StatsService')
                ->replaceArgument(1, $receiverNames);
        }
    }
}
