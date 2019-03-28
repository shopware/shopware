<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

class CacheController extends AbstractController
{
    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(TagAwareAdapterInterface $cache, Serializer $serializer)
    {
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/api/v{version}/_action/cache", name="api.action.cache.delete", methods={"DELETE"})
     */
    public function clearCache(): JsonResponse
    {
        $response = $this->cache->clear();

        return new JsonResponse(['success' => $response]);
    }

    /**
     * @Route("/api/v{version}/_action/cache/item", name="api.action.cache.delete-items", methods={"DELETE"})
     *
     * @throws InvalidRequestParameterException
     */
    public function deleteCacheItems(Request $request): JsonResponse
    {
        $tags = $this->serializer->decode($request->getContent(), 'json');

        if (!is_array($tags) || empty($tags)) {
            throw new InvalidRequestParameterException('tags');
        }

        $tags = array_map(function ($tag) {
            if (!is_string($tag)) {
                throw new InvalidRequestParameterException('tags');
            }

            return $tag;
        }, $tags);

        try {
            $response = $this->cache->deleteItems($tags);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }

        return new JsonResponse(['success' => $response]);
    }

    /**
     * @Route("/api/v{version}/_action/cache/tag", name="api.action.cache.invalidate-tags", methods={"DELETE"})
     *
     * @throws InvalidRequestParameterException
     */
    public function invalidateTags(Request $request): JsonResponse
    {
        $tags = $this->serializer->decode($request->getContent(), 'json');

        if (!is_array($tags) || empty($tags)) {
            throw new InvalidRequestParameterException('tags');
        }

        $tags = array_map(function ($tag) {
            if (!is_string($tag)) {
                throw new InvalidRequestParameterException('tags');
            }

            return $tag;
        }, $tags);

        try {
            $response = $this->cache->invalidateTags($tags);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }

        return new JsonResponse(['success' => $response]);
    }
}
