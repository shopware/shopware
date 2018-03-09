<?php declare(strict_types=1);

namespace Shopware\Rest\Routing;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\DefinitionRegistry;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\FieldCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteCollector
{
    public const DETAIL_ACTION = 'Shopware\Rest\Controller\ApiController::detailAction';

    public const LIST_ACTION = 'Shopware\Rest\Controller\ApiController::listAction';

    public const CREATE_ACTION = 'Shopware\Rest\Controller\ApiController::createAction';

    public const UPDATE_ACTION = 'Shopware\Rest\Controller\ApiController::updateAction';

    public const DELETE_ACTION = 'Shopware\Rest\Controller\ApiController::deleteAction';

    public function collect(): RouteCollection
    {
        return $this->createRouteClasses();
    }

    private function createRouteClasses(): RouteCollection
    {
        $collection = new RouteCollection();

        $route = new Route('/api/{path}');
        $route->setMethods(['GET']);
        $route->setDefault('_controller', self::LIST_ACTION);
        $route->addRequirements(['path' => '.*']);
        $collection->add('api_controller.list', $route);

        $route = new Route('/api/{path}');
        $route->setMethods(['GET']);
        $route->setDefault('_controller', self::DETAIL_ACTION);
        $route->addRequirements(['path' => '.*']);
        $collection->add('api_controller.detail', $route);

        $route = new Route('/api/{path}');
        $route->setMethods(['POST']);
        $route->setDefault('_controller', self::CREATE_ACTION);
        $route->addRequirements(['path' => '.*']);
        $collection->add('api_controller.create', $route);

        $route = new Route('/api/{path}');
        $route->setMethods(['PATCH']);
        $route->setDefault('_controller', self::UPDATE_ACTION);
        $route->addRequirements(['path' => '.*']);
        $collection->add('api_controller.update', $route);

        $route = new Route('/api/{path}');
        $route->setMethods(['DELETE']);
        $route->setDefault('_controller', self::DELETE_ACTION);
        $route->addRequirements(['path' => '.*']);
        $collection->add('api_controller.delete', $route);

        return $collection;
    }
}
