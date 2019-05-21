<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Logging;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LoggingController extends AbstractController
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @Route("/api/v{version}/_action/logs", name="api.action.logs.get", methods={"GET"})
     */
    public function getLogs(): Response
    {
//        $data = json_encode($this->connection->executeQuery('SELECT * FROM logging LIMIT 10')->fetchAll());
//        $data = json_encode($this->connection->executeQuery("SELECT * FROM logging where JSON_SEARCH('content', 'all','level') = 4 LIMIT 10")->fetchAll());
        $data = json_encode($this->connection->executeQuery('SELECT * FROM logging LIMIT 10')->fetchAll());

        return new Response($data, Response::HTTP_OK);
    }

    /**
     * @Route("/api/v{version}/_action/logs/search", name="api.action.logs.search", methods={"POST"})
     */
    public function search(Request $request): Response
    {
        //"$.source" = 'core'
        $searchTerm = $request->request->get('searchTerm');
        $data = json_encode($this->connection->executeQuery('SELECT * FROM logging where `content`->' . $searchTerm)->fetchAll());

        return new Response($data, Response::HTTP_OK);
    }
}
