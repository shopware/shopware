<?php declare(strict_types=1);

namespace Shopware\Api\Routing;

use Shopware\Api\Entity\DefinitionRegistry;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\FieldCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteCollector
{
    const DETAIL_ACTION = 'Shopware\Api\Controller\ApiController::detailAction';

    const LIST_ACTION = 'Shopware\Api\Controller\ApiController::listAction';

    const CREATE_ACTION = 'Shopware\Api\Controller\ApiController::createAction';

    const UPDATE_ACTION = 'Shopware\Api\Controller\ApiController::updateAction';

    const DELETE_ACTION = 'Shopware\Api\Controller\ApiController::deleteAction';

    /**
     * @var DefinitionRegistry
     */
    private $registry;

    public function __construct(DefinitionRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function collect(): RouteCollection
    {
        $collection = new RouteCollection();

        foreach ($this->registry->getElements() as $definition) {
            /* @var EntityDefinition $definition */
            try {
                $definition::getRepositoryClass();
            } catch (\Exception $e) {
                //mapping tables has no repository, skip them
                continue;
            }

            $collection->addCollection(
                $this->getDefinitionRoutes($definition)
            );
        }

        return $collection;
    }

    private function formatEntityName(string $entity): string
    {
        return str_replace('_', '-', $entity);
    }

    private function camelCaseToSnakeCase(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    /**
     * @param string|EntityDefinition $definition
     *
     * @return RouteCollection
     */
    private function getDefinitionRoutes(string $definition): RouteCollection
    {
        $naming = 'api.' . $definition::getEntityName();

        $path = '/api/' . $this->formatEntityName($definition::getEntityName());

        $uuidPrefix = $this->snakeCaseToCamelCase($definition::getEntityName());
        $uuidPrefix = $uuidPrefix[0];

        $collection = $this->createRouteClasses($path, $naming, $uuidPrefix);

        $path .= '/{' . $uuidPrefix . 'Id' . '}';

        /** @var FieldCollection $associations */
        $associations = $definition::getFields()->filterInstance(AssociationInterface::class);

        /** @var AssociationInterface $association */
        foreach ($associations as $association) {
            $collection->addCollection(
                $this->buildAssociationRoutes($uuidPrefix, $definition, $naming, $path, $association)
            );
        }

        return $collection;
    }

    private function buildAssociationRoutes(string $uuidPrefix, string $definition, string $naming, string $path, AssociationInterface $association): RouteCollection
    {
        /** @var EntityDefinition|string $reference */
        $reference = $association->getReferenceClass();
        if ($reference === $definition) {
            return new RouteCollection();
        }

        /** @var Field|AssociationInterface $association */
        $name = $this->camelCaseToSnakeCase($association->getPropertyName());

        $naming .= '.' . $name;
        $path .= '/' . $this->formatEntityName($name);

        $uuidPrefix .= $association->getPropertyName()[0];

        $collection = $this->createRouteClasses($path, $naming, $uuidPrefix);

        $path .= '/{' . $uuidPrefix . 'Id' . '}';

        /** @var FieldCollection $associations */
        $associations = $reference::getFields()->filterInstance(AssociationInterface::class);
        $associations = $associations->getBasicProperties();

        /** @var AssociationInterface $nested */
        foreach ($associations as $nested) {
            $collection->addCollection(
                $this->buildAssociationRoutes($uuidPrefix, $reference, $naming, $path, $nested)
            );
        }

        return $collection;
    }

    private function createRouteClasses(string $path, string $naming, string $uuidPrefix): RouteCollection
    {
        $collection = new RouteCollection();

        $route = new Route($path);
        $route->setMethods(['GET']);
        $route->setDefault('_controller', self::LIST_ACTION);
        $collection->add($naming . '.list', $route);

        $route = new Route($path);
        $route->setMethods(['POST']);
        $route->setDefault('_controller', self::CREATE_ACTION);
        $collection->add($naming . '.create', $route);

        $path .= '/{' . $uuidPrefix . 'Id' . '}';

        $route = new Route($path);
        $route->setMethods(['PATCH']);
        $route->setDefault('_controller', self::UPDATE_ACTION);
        $collection->add($naming . '.update', $route);

        $route = new Route($path);
        $route->setMethods(['GET']);
        $route->setDefault('_controller', self::DETAIL_ACTION);
        $collection->add($naming . '.detail', $route);

        $route = new Route($path);
        $route->setMethods(['DELETE']);
        $route->setDefault('_controller', self::DELETE_ACTION);
        $collection->add($naming . '.delete', $route);

        return $collection;
    }

    private function snakeCaseToCamelCase(string $string)
    {
        $explode = explode('_', $string);
        $explode = array_map('ucfirst', $explode);

        return lcfirst(implode($explode));
    }
}
