<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Api;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 *
 * @deprecated tag:v6.5.0 - Will be removed, migrations should be executed over the CLI instead
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

    /**
     * @internal
     */
    public function __construct(
        MigrationCollectionLoader $loader,
        string $shopwareVersion
    ) {
        $this->loader = $loader;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/database/sync-migration", name="api.action.database.sync-migration", methods={"POST"}, defaults={"_acl"={"system:core:update"}})
     */
    public function syncMigrations(Request $request): Response
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'The `database` endpoint is deprecated and will be removed in v6.5.0.0, migrations should be executed over the CLI instead'
        );

        $this->getCollection($request, MigrationCollectionLoader::VERSION_SELECTION_ALL)->sync();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
    * @Since("6.0.0.0")
    * @Route("/api/_action/database/migrate", name="api.action.database.migrate", methods={"POST"}, defaults={"_acl"={"system:core:update"}})
    */
    public function migrate(Request $request): Response
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'The `database` endpoint is deprecated and will be removed in v6.5.0.0, migrations should be executed over the CLI instead'
        );

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
    * @Route("/api/_action/database/migrate-destructive", name="api.action.database.migrate-destructive", methods={"POST"}, defaults={"_acl"={"system:core:update"}})
    */
    public function migrateDestructive(Request $request): Response
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'The `database` endpoint is deprecated and will be removed in v6.5.0.0, migrations should be executed over the CLI instead'
        );

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
