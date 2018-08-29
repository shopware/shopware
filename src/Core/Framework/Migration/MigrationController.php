<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
     * @Route("/migration/add", name="migration.add")
     */
    public function addMigrations(Request $request)
    {
        foreach ($this->directories as $namespace => $directory) {
            $this->collector->addDirectory($directory, $namespace);
        }

        $this->collector->syncMigrationCollection();
    }

    /**
     * @Route("/migration/migrate", name="migration.migrate")
     */
    public function migrate(Request $request)
    {
        $destructive = (bool) $request->get('destructive', false);
        $limit = (int) $request->get('limit', 0);

        $this->runner->migrate($destructive, $limit);
    }
}
