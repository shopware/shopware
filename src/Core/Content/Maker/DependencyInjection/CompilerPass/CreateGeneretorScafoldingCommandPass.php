<?php declare(strict_types=1);

namespace Shopware\Core\Content\Maker\DependencyInjection\CompilerPass;

use Shopware\Core\Content\Maker\Command\MakerCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

#[Package('content')]
class CreateGeneretorScafoldingCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $generators = $container->findTaggedServiceIds('shopware.scaffold.generator');

        foreach ($generators as $id => $tags) {
            $def = $container->getDefinition($id);
            if ($def->isDeprecated()) {
                continue;
            }

            $class = $container->getParameterBag()->resolveValue($def->getClass());
            if (!is_subclass_of($class, ScaffoldingGenerator::class)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, ScaffoldingGenerator::class));
            }

            $commandDefinition = new ChildDefinition('maker.auto_command.abstract');
            $commandDefinition->setClass(MakerCommand::class);
            $commandDefinition->replaceArgument(0, new Reference($id));

            /** @var class-string $class */
            $class = $def->getClass();
            $ref = new \ReflectionClass($class);

            $tagAttributes = ['command' => 'make:' . self::asCommand($ref->getShortName())];

            $commandDefinition->addTag('console.command', $tagAttributes);

            $container->setDefinition(
                sprintf('make.auto_command.%s', self::asCommand($ref->getShortName())),
                $commandDefinition
            );
        }
    }

    private static function asCommand(string $value): string
    {
        $snakeCaseConverter = new CamelCaseToSnakeCaseNameConverter();

        $value = str_replace('_', '-', $snakeCaseConverter->normalize($value));

        return str_replace('-generator', '', $value);
    }

    /**
     * (e.g. `BlogPostType` -> `blog_post_type`).
     */
    private static function asSnake(string $value): string
    {
        $value = trim($value);
        $value = (string) preg_replace('/[^a-zA-Z0-9_]/', '_', $value);
        $value = (string) preg_replace('/(?<=\\w)([A-Z])/', '_$1', $value);
        $value = (string) preg_replace('/_{2,}/', '_', $value);

        return strtolower($value);
    }
}
