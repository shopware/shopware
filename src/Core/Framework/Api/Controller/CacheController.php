<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\Exception\InvalidParameterException;
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
     *
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        $response = $this->cache->clear();

        return new JsonResponse(['success' => $response]);
    }

    /**
     * @Route("/api/v{version}/_action/cache/item", name="api.action.cache.delete-items", methods={"DELETE"})
     *
     * @param Request $request
     *
     * @throws InvalidParameterException
     *
     * @return JsonResponse
     */
    public function deleteCacheItems(Request $request): JsonResponse
    {
        $keys = $this->serializer->decode($request->getContent(), 'json');

        if (!is_array($keys) || empty($keys)) {
            throw new InvalidParameterException('Expected keys as payload array');
        }

        $keys = array_map(function ($key) {
            if (!is_string($key)) {
                throw new InvalidParameterException(sprintf('Expected key of type string for %s', $key));
            }

            return $key;
        }, $keys);

        try {
            $response = $this->cache->deleteItems($keys);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }

        return new JsonResponse(['success' => $response]);
    }

    /**
     * @Route("/api/v{version}/_action/cache/tag", name="api.action.cache.invalidate-tags", methods={"DELETE"})
     *
     * @throws InvalidParameterException
     *
     * @return JsonResponse
     */
    public function invalidateTags(Request $request): JsonResponse
    {
        $tags = $this->serializer->decode($request->getContent(), 'json');

        if (!is_array($tags) || empty($tags)) {
            throw new InvalidParameterException('Expected tags as payload array');
        }

        $tags = array_map(function ($tag) {
            if (!is_string($tag)) {
                throw new InvalidParameterException(sprintf('Expected tag of type string for %s', $tag));
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
