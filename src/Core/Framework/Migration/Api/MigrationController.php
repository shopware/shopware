<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Api;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class MigrationController extends AbstractController
{
    /**
     * @var MigrationCollectionLoader
     */
    private $loader;

    /**
     * @var string
     */
    private $shopwareVersion;

    public function __construct(
        MigrationCollectionLoader $loader,
        string $shopwareVersion
    ) {
        $this->loader = $loader;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/database/sync-migration",
     *     summary="Sync migrations to the database",
     *     description="Reads all migrations of the provided bundle name and inserts them to the `migration` database table.",
     *     operationId="syncMigrations",
     *     tags={"Admin API", "Database Migrations"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="identifier",
     *                 description="Name of the bundle whose migrations are to be synced.",
     *                 type="string",
     *                 default="core"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Returns a no content response indicating a successful sync."
     *     )
     * )
     * @Route("/api/_action/database/sync-migration", name="api.action.database.sync-migration", methods={"POST"})
     * @Acl({"system:core:update"})
     */
    public function syncMigrations(Request $request): Response
    {
        $this->getCollection($request, MigrationCollectionLoader::VERSION_SELECTION_ALL)->sync();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/database/migrate",
     *     summary="Execute migrations",
     *     description="Executes all migrations for the provided bundle name.",
     *     operationId="migrate",
     *     tags={"Admin API", "Database Migrations"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="identifier",
     *                 description="Name of the bundle whose migrations are to be synced.",
     *                 type="string",
     *                 default="core"
     *             ),
     *             @OA\Property(
     *                 property="limit",
     *                 description="Limit the amout of migrations to be executed. By default, there is no limit.",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="until",
     *                 description="A timestamp that controls until which `creation_date` migrations are executed.
By default, all migrations are executed.",
     *                 type="integer"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Returns a no content response indicating a successful sync."
     *     )
     * )
     * @Route("/api/_action/database/migrate", name="api.action.database.migrate", methods={"POST"})
     * @Acl({"system:core:update"})
     */
    public function migrate(Request $request): Response
    {
        if (!($limit = $request->request->getInt('limit'))) {
            $limit = null;
        }

        if (!($until = $request->request->getInt('until'))) {
            $until = null;
        }

        $collection = $this->getCollection($request, MigrationCollectionLoader::VERSION_SELECTION_ALL);

        try {
            $collection->migrateInPlace($until, $limit);
        } catch (\Exception $e) {
            throw new MigrateException($e->getMessage(), $e);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/database/migrate-destructive",
     *     summary="Execute destructive migrations",
     *     description="Executes all destructive migrations for the provided bundle name.",
     *     operationId="migrateDestructive",
     *     tags={"Admin API", "Database Migrations"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="identifier",
     *                 description="Name of the bundle whose migrations are to be synced.",
     *                 type="string",
     *                 default="core"
     *             ),
     *             @OA\Property(
     *                 property="mode",
     *                 description=" The mode defines what type of migrations are executed.
* Possible values:
* `all`: Execute all migrations
* `blue-green`: Blue-green safe
    * update from 6.a.* to 6.(a+1).0 -> migrations for major 6.a are NOT executed
    * rollback from 6.(a+1).0 to 6.a.* is still possible
    * update from 6.(a+1).0 to 6.(a+1).1 or higher - migrations for major 6.a are executed
    * rollback possible from 6.(a+1).1 to 6.(a+1).0 possible
    * but rollback to 6.a.* not possible anymore!
* `safe`: Executing the migrations of the penultimate major. This should always be safe",
     *                 enum={"all", "blue-green", "safe"},
     *                 type="string",
     *                 default="safe"
     *             ),
     *             @OA\Property(
     *                 property="limit",
     *                 description="Limit the amout of migrations to be executed. By default, there is no limit.",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="until",
     *                 description="A timestamp that controls until which `creation_date` migrations are executed.
    By default, all migrations are executed.",
     *                 type="integer"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Returns a no content response indicating a successful sync."
     *     )
     * )
     * @Route("/api/_action/database/migrate-destructive", name="api.action.database.migrate-destructive", methods={"POST"})
     * @Acl({"system:core:update"})
     */
    public function migrateDestructive(Request $request): Response
    {
        if (!($limit = $request->request->getInt('limit'))) {
            $limit = null;
        }

        if (!($until = $request->request->getInt('until'))) {
            $until = null;
        }

        $mode = $request->request->get('mode', MigrationCollectionLoader::VERSION_SELECTION_SAFE);
        $collection = $this->getCollection($request, (string) $mode);

        try {
            $collection->migrateDestructiveInPlace($until, $limit);
        } catch (\Exception $e) {
            throw new MigrateException($e->getMessage(), $e);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function getCollection(Request $request, string $mode): MigrationCollection
    {
        $identifier = (string) $request->request->get('identifier', 'core');

        if ($identifier === 'core') {
            return $this->loader->collectAllForVersion($this->shopwareVersion, $mode);
        }

        return $this->loader->collect($identifier);
    }
}
