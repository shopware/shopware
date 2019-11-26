<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Api;

use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
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

    /**
     * @var MigrationRuntime
     */
    private $runner;

    public function __construct(
        MigrationCollectionLoader $loader,
        MigrationRuntime $runner
    ) {
        $this->loader = $loader;
        $this->runner = $runner;
    }

    /**
     * @Route("/api/v{version}/_action/database/sync-migration", name="api.action.database.sync-migration", methods={"POST"})
     */
    public function syncMigrations(Request $request): Response
    {
        $this->loader->syncMigrationCollection($request->request->get('identifier', MigrationCollectionLoader::SHOPWARE_CORE_MIGRATION_IDENTIFIER));

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

        $generator = $this->runner->migrate($until, $limit);

        return $this->migrateGenerator($generator);
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

        $generator = $this->runner->migrateDestructive($until, $limit);

        return $this->migrateGenerator($generator);
    }

    private function migrateGenerator(\Generator $generator): Response
    {
        try {
            while ($generator->valid()) {
                $generator->next();
            }
        } catch (\Exception $e) {
            throw new MigrateException($e->getMessage());
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
