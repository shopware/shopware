<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class IndexingController extends AbstractController
{
    /**
     * @var IndexerRegistryInterface
     */
    private $registry;

    public function __construct(IndexerRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Starts the dal indexing process in batch mode
     *
     * @Route("/api/v{version}/_action/indexing", name="api.action.indexing", methods={"POST"})
     */
    public function indexing(Request $request): JsonResponse
    {
        $lastIndexer = $request->get('lastIndexer');
        $offset = $request->get('offset');
        $time = $request->get('timestamp');

        $time = $time ? new \DateTime($time) : new \DateTime();

        $result = $this->registry->partial($lastIndexer, $offset, $time);

        if (!$result) {
            return new JsonResponse(['done' => true]);
        }

        return new JsonResponse([
            'lastIndexer' => $result->getIndexer(),
            'offset' => $result->getOffset(),
            'timestamp' => $time->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
