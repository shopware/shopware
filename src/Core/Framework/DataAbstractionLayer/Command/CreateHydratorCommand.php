<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'dal:create:hydrators',
    description: 'Creates the hydrator classes',
)]
#[Package('core')]
class CreateHydratorCommand extends Command
{
    private readonly string $dir;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        string $rootDir
    ) {
        parent::__construct();
        $this->dir = $rootDir . '/platform/src';
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('whitelist', InputArgument::IS_ARRAY);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->hasInactiveFeatureFlag()) {
            throw new \RuntimeException('You have to enable all feature flags when running this command. Simply add FEATURE_ALL=major to your .env file');
        }

        $io = new ShopwareStyle($input, $output);
        $io->title('DAL generate hydrators');

        if (!file_exists($this->dir)) {
            mkdir($this->dir);
        }

        $entities = $this->registry->getDefinitions();
        $classes = [];
        $services = [];

        $whitelist = $input->getArgument('whitelist');
        if (empty($whitelist)) {
            $whitelist = [];

            $startsWith = ['product', 'category', 'property'];

            foreach ($entities as $definition) {
                foreach ($startsWith as $prefix) {
                    if (str_starts_with($definition->getEntityName(), $prefix)) {
                        $whitelist[] = $definition->getEntityName();

                        break;
                    }
                }
            }
        }

        foreach ($entities as $entity) {
            if (!\in_array($entity->getEntityName(), $whitelist, true)) {
                continue;
            }
            if ($entity instanceof EntityTranslationDefinition) {
                continue;
            }
            if ($entity instanceof MappingEntityDefinition) {
                continue;
            }
            $classes[$this->getFile($entity)] = $this->generate($entity);

            $content = $this->updateDefinition($entity);
            if ($content !== null) {
                $classes[$this->getDefinitionFile($entity)] = $content;
            }

            $services[] = $this->generateService($entity);
        }

        $io->success('Created schema in ' . $this->dir);
        foreach ($classes as $file => $content) {
            $file = rtrim($this->dir, '/') . '/' . $file;

            try {
                file_put_contents($file, $content);
            } catch (\Throwable $e) {
                $output->writeln($e->getMessage());
            }
        }

        $file = $this->dir . '/Core/Framework/DependencyInjection/hydrator.xml';

        try {
            $content = <<<EOF
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
#services#
    </services>
</container>

EOF;

            $content = str_replace('#services#', implode("\n\n", $services), $content);

            file_put_contents($file, $content);
        } catch (\Throwable $e) {
            $output->writeln($e->getMessage());
        }

        return Command::SUCCESS;
    }

    private function getDefinitionFile(EntityDefinition $definition): string
    {
        $class = $definition::class;

        $class = explode('\\', $class);

        array_shift($class);

        $class = implode('/', $class);

        return $class . '.php';
    }

    private function updateDefinition(EntityDefinition $definition): ?string
    {
        $file = $this->getDefinitionFile($definition);

        $file = rtrim($this->dir, '/') . '/' . $file;

        $content = (string) file_get_contents($file);

        if (str_contains($content, 'getHydratorClass')) {
            return null;
        }

        $find = 'protected function defineFields(';

        $class = $this->getClass($definition);

        $replace = <<<EOF
public function getHydratorClass(): string
    {
        return #class#::class;
    }

    protected function defineFields(
EOF;

        $replace = str_replace('#class#', $class, $replace);

        $content = str_replace($find, $replace, $content);

        return $content;
    }

    private function generateService(EntityDefinition $definition): string
    {
        $template = <<<EOF
        <service id="#namespace#\#class#" public="true">
            <argument type="service" id="service_container"/>
        </service>
EOF;

        $vars = [
            '#namespace#' => $this->getNamespace($definition),
            '#class#' => $this->getClass($definition),
        ];

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    private function generate(EntityDefinition $definition): string
    {
        $order = array_merge(
            $definition->getFields()->filterInstance(StorageAware::class)->getElements(),
            $definition->getFields()->filterInstance(TranslatedField::class)->getElements(),
            $definition->getFields()->filterInstance(ManyToOneAssociationField::class)->getElements(),
            $definition->getFields()->filterInstance(OneToOneAssociationField::class)->getElements(),
            $definition->getFields()->filterInstance(ManyToManyAssociationField::class)->getElements(),
            $definition->getFields()->getElements(),
        );

        $fields = [];
        $calls = [];

        $handled = [];
        foreach ($order as $field) {
            if (\in_array($field->getPropertyName(), $handled, true)) {
                continue;
            }

            if (!$this->hasProperty($definition, $field)) {
                continue;
            }

            $handled[] = $field->getPropertyName();

            if ($field instanceof TranslatedField) {
                $typed = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);

                if ($typed instanceof CustomFields) {
                    $calls[] = $this->renderCustomFields($field);
                }

                continue;
            }
            if ($field instanceof ParentAssociationField) {
                continue;
            }
            if ($field instanceof OneToManyAssociationField) {
                continue;
            }
            if ($field instanceof JsonField && $field->getPropertyName() === 'translated') {
                continue;
            }
            if ($field->is(Runtime::class)) {
                continue;
            }
            if ($field instanceof CustomFields) {
                $calls[] = $this->renderCustomFields($field);
            }
            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $fields[] = $this->renderToOne($field);

                continue;
            }
            if ($field instanceof ManyToManyAssociationField) {
                $calls[] = $this->renderManyToMany($field);

                continue;
            }

            $fields[] = $this->renderField($field);
        }

        return $this->renderClass(
            $definition,
            $this->getNamespace($definition),
            $this->getClass($definition),
            $fields,
            $calls
        );
    }

    private function getNamespace(EntityDefinition $definition): string
    {
        $reflection = new \ReflectionClass($definition);

        return $reflection->getNamespaceName();
    }

    private function getClass(EntityDefinition $definition): string
    {
        $parts = explode('_', (string) $definition->getEntityName());

        $parts = array_map('ucfirst', $parts);

        return implode('', $parts) . 'Hydrator';
    }

    private function renderClass(EntityDefinition $definition, string $namespace, string $class, array $fields, array $calls): string
    {
        $template = <<<EOF
<?php declare(strict_types=1);

namespace #namespace#;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;

class #class# extends EntityHydrator
{
    protected function assign(EntityDefinition \$definition, Entity \$entity, string \$root, array \$row, Context \$context): Entity
    {

        #fields#

        \$this->translate(\$definition, \$entity, \$row, \$root, \$context, \$definition->getTranslatedFields());
        \$this->hydrateFields(\$definition, \$entity, \$root, \$row, \$context, \$definition->getExtensionFields());#calls#

        return \$entity;
    }
}

EOF;

        $entity = explode('\\', (string) $definition->getEntityClass());
        $entity = array_pop($entity);

        $callTemplate = '';
        if (!empty($calls)) {
            $callTemplate = "\n        " . implode("\n        ", $calls);
        }

        $vars = [
            '#namespace#' => $namespace,
            '#class#' => $class,
            '#entity#' => $entity,
            '#fields#' => implode("\n        ", $fields),
            '#calls#' => $callTemplate,
        ];

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    private function renderToOne(AssociationField $field): string
    {
        $template = <<<EOF
        \$entity->#property# = \$this->manyToOne(\$row, \$root, \$definition->getField('#property#'), \$context);
        EOF;

        return str_replace('#property#', $field->getPropertyName(), $template);
    }

    private function renderManyToMany(ManyToManyAssociationField $field): string
    {
        $template = <<<EOF
        \$this->manyToMany(\$row, \$root, \$entity, \$definition->getField('#property#'));
        EOF;

        return str_replace('#property#', $field->getPropertyName(), $template);
    }

    private function renderField(Field $field): string
    {
        $template = 'if (isset($row[$root . \'.#property#\'])) {
            #inner#
        }';
        $arrayKeyExists = "if (\array_key_exists(\$root . '.#property#', \$row)) {
            #inner#
        }";
        switch (true) {
            case $field instanceof IdField:
            case $field instanceof FkField:
            case $field instanceof VersionField:
            case $field instanceof ReferenceVersionField:
                $inner = str_replace('#property#', $field->getPropertyName(), '$entity->#property# = Uuid::fromBytesToHex($row[$root . \'.#property#\']);');

                break;
            case $field instanceof StringField:
                $inner = str_replace('#property#', $field->getPropertyName(), '$entity->#property# = $row[$root . \'.#property#\'];');

                break;
            case $field instanceof FloatField:
                $inner = str_replace('#property#', $field->getPropertyName(), '$entity->#property# = (float) $row[$root . \'.#property#\'];');

                break;
            case $field instanceof IntField:
                $inner = str_replace('#property#', $field->getPropertyName(), '$entity->#property# = (int) $row[$root . \'.#property#\'];');

                break;
            case $field instanceof DateField:
            case $field instanceof DateTimeField:
                $inner = str_replace('#property#', $field->getPropertyName(), "\$entity->#property# = new \DateTimeImmutable(\$row[\$root . '.#property#']);");

                break;
            case $field instanceof BoolField:
                $inner = str_replace('#property#', $field->getPropertyName(), '$entity->#property# = (bool) $row[$root . \'.#property#\'];');

                break;
            default:
                $template = $arrayKeyExists;
                $inner = str_replace('#property#', $field->getPropertyName(), '$entity->#property# = $definition->decode(\'#property#\', self::value($row, $root, \'#property#\'));');

                return str_replace(['#property#', '#inner#'], [$field->getPropertyName(), $inner], $template);
        }

        return str_replace(['#property#', '#inner#'], [$field->getPropertyName(), $inner], $template);
    }

    private function renderCustomFields(Field $field): string
    {
        $template = <<<EOF
        \$this->customFields(\$definition, \$row, \$root, \$entity, \$definition->getField('#property#'), \$context);
        EOF;

        return str_replace('#property#', $field->getPropertyName(), $template);
    }

    private function getFile(EntityDefinition $definition): string
    {
        $namespace = $this->getNamespace($definition);

        $namespace = explode('\\', $namespace);

        array_shift($namespace);

        $namespace = implode('/', $namespace);

        return $namespace . '/' . $this->getClass($definition) . '.php';
    }

    private function hasProperty(EntityDefinition $definition, Field $field): bool
    {
        if ($definition->getEntityClass() === ArrayEntity::class) {
            return true;
        }

        return property_exists($definition->getEntityClass(), $field->getPropertyName());
    }

    private function hasInactiveFeatureFlag(): bool
    {
        foreach (Feature::getAll() as $enabled) {
            if ($enabled === false) {
                return true;
            }
        }

        return false;
    }
}
