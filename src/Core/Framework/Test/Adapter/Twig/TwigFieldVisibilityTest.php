<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

class TwigFieldVisibilityTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testInternalFieldsAreNotVisibleInTwig(): void
    {
        $definition = $this->getContainer()->get(CustomerDefinition::class);
        $entity = new CustomerEntity();
        $entity->internalSetEntityData($definition->getEntityName(), $definition->getFieldVisibility());

        $twig = new TwigEnvironment(new ArrayLoader([
            'json-encode.twig' => file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/json-encode.twig'),
            'get-vars.twig' => file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/get-vars.twig'),
            'implicit-get.twig' => str_replace(
                '##property_name##',
                'password',
                file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/implicit-get.twig')
            ),
            'explicit-get.twig' => str_replace(
                '##property_getter##',
                'getPassword',
                file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/explicit-get.twig')
            ),
            'offset-get.twig' => str_replace(
                '##property_name##',
                'password',
                file_get_contents(__DIR__ . '/fixtures/FieldVisibilityCases/offset-get.twig')
            ),
        ]));

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
            $twig->render('implicit-get.twig', ['object' => $entity]);
        } catch (RuntimeError $e) {
            $innerException = $e->getPrevious();
        }
        static::assertInstanceOf(
            InternalFieldAccessNotAllowedException::class,
            $innerException,
            sprintf(
                'It was possible to call getter for property %s on entity %s, but the property is not ApiAware, therefore access to that property in twig contexts is prohibited, please ensure to call the `$this->checkIfPropertyAccessIsAllowed("propertyName")` in the getter of that property.',
                'password',
                CustomerEntity::class
            )
        );

        $innerException = null;

        try {
            $twig->render('explicit-get.twig', ['object' => $entity]);
        } catch (RuntimeError $e) {
            $innerException = $e->getPrevious();
        }
        static::assertInstanceOf(
            InternalFieldAccessNotAllowedException::class,
            $innerException,
            sprintf(
                'It was possible to call getter for property %s on entity %s, but the property is not ApiAware, therefore access to that property in twig contexts is prohibited, please ensure to call the `$this->checkIfPropertyAccessIsAllowed("propertyName")` in the getter of that property.',
                'password',
                CustomerEntity::class
            )
        );
    }
}
