<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Api;

use Doctrine\DBAL\Connection;
use OpenApi\Annotations as OA;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\CustomEntity\Hook\StoreApiHook;
use Shopware\Core\System\SalesChannel\GenericStoreApiResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal Should not be overwritten, serves as access point for various custom entities
 * @RouteScope(scopes={"store-api"})
 */
class CustomEntityStoreApiRoute
{
    private ScriptExecutor $executor;

    private Connection $connection;

    public function __construct(ScriptExecutor $executor, Connection $connection)
    {
        $this->executor = $executor;
        $this->connection = $connection;
    }

    /**
     * @Since("6.4.9.0")
     * @OA\Post(
     *      path="/custom-entity-{entity}",
     *      summary="Access point for different custom entity store api routes",
     *      operationId="customEntityStoreApi",
     *      tags={"Store API","Custom entity"},
     *      @OA\Parameter(
     *          name="entity",
     *          description="Name of the entity",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns different structures of results based on the corresponding entity and the called script.",
     *     )
     * )
     * @Route("/store-api/custom-entity-{entity}", name="store-api.custom_entity", methods={"POST"})
     */
    public function load(string $entity, Request $request, SalesChannelContext $context): GenericStoreApiResponse
    {
        $entity = 'custom_entity_' . \str_replace('-', '_', $entity);

        $response = new ScriptResponse();

        $this->validate($entity);

        $this->executor->execute(
            new StoreApiHook($entity, $request->request->all(), $response, $context)
        );

        return new GenericStoreApiResponse(
            $response->code,
            new ArrayStruct($response->body, 'store_api_' . $entity . '_response')
        );
    }

    private function validate(string $entity): void
    {
        $aware = $this->connection->fetchOne('SELECT store_api_aware FROM custom_entity WHERE name = :name', ['name' => $entity]);

        if (!$aware) {
            throw new HttpException('CUSTOM_ENTITY_STORE_API_RESTRICTION', Response::HTTP_BAD_REQUEST, 'Custom entity {entity} not found or not api aware', ['entity' => $entity]);
        }
    }
}
