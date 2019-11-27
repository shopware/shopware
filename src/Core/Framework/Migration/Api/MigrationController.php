<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Api;

use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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

    public function __construct(
        MigrationCollectionLoader $loader
    ) {
        $this->loader = $loader;
    }

    /**
     * @Route("/api/v{version}/_action/database/sync-migration", name="api.action.database.sync-migration", methods={"POST"})
     */
    public function syncMigrations(Request $request): Response
    {
        $this->getCollection($request)->sync();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/database/migrate", name="api.action.database.migrate", methods={"POST"})
     */
    public function migrate(Request $request): Response
    {
        if (!($limit = $request->request->getInt('limit'))) {
            $limit = null;
        }

        if (!($until = $request->request->getInt('until'))) {
            $until = null;
        }

        $collection = $this->getCollection($request);

        try {
            $collection->migrateInPlace($until, $limit);
        } catch (\Exception $e) {
            throw new MigrateException($e->getMessage(), $e);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/database/migrate-destructive", name="api.action.database.migrate-destructive", methods={"POST"})
     */
    public function migrateDestructive(Request $request): Response
    {
        if (!($limit = $request->request->getInt('limit'))) {
            $limit = null;
        }

        if (!($until = $request->request->getInt('until'))) {
            $until = null;
        }

        $collection = $this->getCollection($request);

        try {
            $collection->migrateDestructiveInPlace($until, $limit);
        } catch (\Exception $e) {
            throw new MigrateException($e->getMessage(), $e);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function getCollection(Request $request): MigrationCollection
    {
        return $this->loader->collect($request->request->get('identifier', 'core'));
    }
}
