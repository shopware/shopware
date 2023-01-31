<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use function method_exists;
use function sprintf;
use function ucfirst;

/**
 * @internal
 */
class TwigFieldVisibilityTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testInternalFieldsAreNotVisibleInTwig(): void
    {
        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        foreach ($definitionRegistry->getDefinitions() as $definition) {
            /** @var CompiledFieldCollection $internalFields */
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
        static::assertInstanceOf(InternalFieldAccessNotAllowedException::class, $innerException);

        $innerException = null;

        try {
            $result = $twig->render('implicit-get.twig', ['object' => $entity]);
        } catch (RuntimeError $e) {
            $innerException = $e->getPrevious();
        }

        // When the entity class don't have an explicit getter the magic methods will be called. As the isset/exists method returns false for protected fields the getter will not called
        if (method_exists($entity, 'get' . ucfirst($propertyName))) {
            static::assertInstanceOf(
                InternalFieldAccessNotAllowedException::class,
                $innerException,
                sprintf(
                    'It was possible to call getter for property %s on entity %s, but the property is not ApiAware, therefore access to that property in twig contexts is prohibited, please ensure to call the `$this->checkIfPropertyAccessIsAllowed("propertyName")` in the getter of that property.',
                    $propertyName,
                    $entity::class
                )
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

        // When the entity class don't have an explicit getter the magic methods will be called. As the isset/exists method returns false for protected fields the getter will not called
        if (method_exists($entity, 'get' . ucfirst($propertyName))) {
            static::assertInstanceOf(
                InternalFieldAccessNotAllowedException::class,
                $innerException,
                sprintf(
                    'It was possible to call getter for property %s on entity %s, but the property is not ApiAware, therefore access to that property in twig contexts is prohibited, please ensure to call the `$this->checkIfPropertyAccessIsAllowed("propertyName")` in the getter of that property.',
                    $propertyName,
                    $entity::class
                )
            );
        } else {
            static::assertStringNotContainsString('password', $result);
        }
    }

    private function initTwig(string $propertyName): Environment
    {
        $propertyGetter = 'get' . ucfirst($propertyName);

        $twig = new TwigEnvironment(new ArrayLoader([
            'json-encode.twig' => file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/json-encode.twig'),
            'get-vars.twig' => file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/get-vars.twig'),
            'implicit-get.twig' => str_replace(
                '##property_name##',
                $propertyName,
                file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/implicit-get.twig')
            ),
            'explicit-get.twig' => str_replace(
                '##property_getter##',
                $propertyGetter,
                file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/explicit-get.twig')
            ),
            'offset-get.twig' => str_replace(
                '##property_name##',
                $propertyName,
                file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/offset-get.twig')
            ),
        ]));

        $twig->addExtension(new PhpSyntaxExtension());

        return $twig;
    }
}
