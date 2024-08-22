<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
class TwigFieldVisibilityTest extends TestCase
{
    use KernelTestBehaviour;

    public function testInternalFieldsAreNotVisibleInTwig(): void
    {
        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);

        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $internalFields = $definition->getFields()
                ->filter(fn (Field $field): bool => !$field->is(ApiAware::class));

            foreach ($internalFields as $field) {
                $this->testAccessibilityForField($definition, $field->getPropertyName(), $definition->getEntityClass());
                $this->testAccessibilityForField($definition, $field->getPropertyName(), PartialEntity::class);
            }
        }
    }

    private function testAccessibilityForField(EntityDefinition $definition, string $propertyName, string $entityClass): void
    {
        $entity = new $entityClass();
        static::assertInstanceOf(Entity::class, $entity);
        $entity->internalSetEntityData($definition->getEntityName(), $definition->getFieldVisibility());

        $twig = $this->initTwig($propertyName);

        $result = $twig->render('json-encode.twig', ['object' => $entity]);
        static::assertStringNotContainsString('password', $result);

        $result = $twig->render('get-vars.twig', ['object' => $entity]);
        static::assertStringNotContainsString('password', $result);

        $innerException = null;

        try {
            $twig->render('offset-get.twig', ['object' => $entity]);
        } catch (RuntimeError $e) {
            $innerException = $e->getPrevious();
        }
        static::assertInstanceOf(DataAbstractionLayerException::class, $innerException);
        static::assertSame(
            \sprintf(
                'Access to property "%s" not allowed on entity "%s".',
                $propertyName,
                $entity::class
            ),
            $innerException->getMessage()
        );

        $innerException = null;

        try {
            $result = $twig->render('implicit-get.twig', ['object' => $entity]);
        } catch (RuntimeError $e) {
            $innerException = $e->getPrevious();
        }

        // When the entity class don't have an explicit getter the magic methods will be called. As the isset/exists method returns false for protected fields the getter will not be called
        if (\method_exists($entity, 'get' . \ucfirst($propertyName))) {
            static::assertInstanceOf(
                DataAbstractionLayerException::class,
                $innerException,
                \sprintf(
                    'It was possible to call getter for property %s on entity %s, but the property is not ApiAware, therefore access to that property in twig contexts is prohibited, please ensure to call the `$this->checkIfPropertyAccessIsAllowed("propertyName")` in the getter of that property.',
                    $propertyName,
                    $entity::class
                )
            );
            static::assertSame(
                \sprintf('Access to property "%s" not allowed on entity "%s".', $propertyName, $entity::class),
                $innerException->getMessage()
            );
        } else {
            static::assertStringNotContainsString('password', $result);
        }

        $innerException = null;

        try {
            $twig->render('explicit-get.twig', ['object' => $entity]);
        } catch (RuntimeError $e) {
            $innerException = $e->getPrevious();
        }

        // When the entity class don't have an explicit getter the magic methods will be called. As the isset/exists method returns false for protected fields the getter will not be called
        if (\method_exists($entity, 'get' . \ucfirst($propertyName))) {
            static::assertInstanceOf(
                DataAbstractionLayerException::class,
                $innerException,
                \sprintf(
                    'It was possible to call getter for property %s on entity %s, but the property is not ApiAware, therefore access to that property in twig contexts is prohibited, please ensure to call the `$this->checkIfPropertyAccessIsAllowed("propertyName")` in the getter of that property.',
                    $propertyName,
                    $entity::class
                )
            );
            static::assertSame(
                \sprintf('Access to property "%s" not allowed on entity "%s".', $propertyName, $entity::class),
                $innerException->getMessage()
            );
        } else {
            static::assertStringNotContainsString('password', $result);
        }
    }

    private function initTwig(string $propertyName): Environment
    {
        $propertyGetter = 'get' . \ucfirst($propertyName);

        $implicitReplace = file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/implicit-get.twig');
        $explicitReplace = file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/explicit-get.twig');
        $offsetReplace = file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/offset-get.twig');

        static::assertIsString($implicitReplace);
        static::assertIsString($explicitReplace);
        static::assertIsString($offsetReplace);

        $twig = new TwigEnvironment(new ArrayLoader([
            'json-encode.twig' => file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/json-encode.twig'),
            'get-vars.twig' => file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/get-vars.twig'),
            'implicit-get.twig' => str_replace(
                '##property_name##',
                $propertyName,
                $implicitReplace
            ),
            'explicit-get.twig' => str_replace(
                '##property_getter##',
                $propertyGetter,
                $explicitReplace
            ),
            'offset-get.twig' => str_replace(
                '##property_name##',
                $propertyName,
                $offsetReplace
            ),
        ]));

        $twig->addExtension(new PhpSyntaxExtension());

        return $twig;
    }
}
