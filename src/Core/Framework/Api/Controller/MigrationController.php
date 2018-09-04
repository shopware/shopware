<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\Exception\MissingMigrateTimstampException;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MigrationController extends Controller
{
    /**
     * @var MigrationCollectionLoader
     */
    private $collector;

    /**
     * @var MigrationRuntime
     */
    private $runner;

    /**
     * @var string[]
     */
    private $directories;

    /**
     * @param string[] $directories
     */
    public function __construct(
        MigrationCollectionLoader $collector,
        MigrationRuntime $runner,
        array $directories
    ) {
        $this->collector = $collector;
        $this->runner = $runner;
        $this->directories = $directories;
    }

    /**
     * @Route("/api/v{version}/migration/add", name="migration.add", methods={"POST"})
     */
    public function addMigrations(): JsonResponse
    {
        foreach ($this->directories as $namespace => $directory) {
            $this->collector->addDirectory($directory, $namespace);
        }

        $this->collector->syncMigrationCollection();

        return new JsonResponse(['message' => 'migrations added to the database']);
    }

    /**
     * @Route("/api/v{version}/migration/migrate", name="migration.migrate", methods={"POST"})
     */
    public function migrate(Request $request): JsonResponse
    {
        $destructive = (bool) $request->get('destructive', false);
        $limit = (int) $request->get('limit', 0);

        if (!($timeStamp = (int) $request->get('timeStamp'))) {
            throw new MissingMigrateTimstampException('timeStamp cap missing');
        }

        try {
            $this->runner->migrate($destructive, $limit, $timeStamp);
        } catch (\Exception $e) {
            throw new MigrateException('Migration Error: "' . $e->getMessage() . '"');
        }

        return new JsonResponse(['message' => 'Migrations executed']);
    }
}
