<?php declare(strict_types=1);

namespace Shopware\Album\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Album\Repository\AlbumRepository;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.album.api_controller", path="/api")
 */
class AlbumController extends ApiController
{
    /**
     * @var AlbumRepository
     */
    private $albumRepository;

    public function __construct(AlbumRepository $albumRepository)
    {
        $this->albumRepository = $albumRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'album';
    }

    public function getXmlChildKey(): string
    {
        return 'album';
    }

    /**
     * @Route("/album.{responseFormat}", name="api.album.list", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function listAction(Request $request, ApiContext $context): Response
    {
        $criteria = new Criteria();

        if ($request->query->has('offset')) {
            $criteria->setOffset((int) $request->query->get('offset'));
        }

        if ($request->query->has('limit')) {
            $criteria->setLimit((int) $request->query->get('limit'));
        }

        if ($request->query->has('query')) {
            $criteria->addFilter(
                QueryStringParser::fromUrl($request->query->get('query'))
            );
        }

        $criteria->setFetchCount(true);

        $album = $this->albumRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $album,
            'total' => $album->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/album/{albumUuid}.{responseFormat}", name="api.album.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('albumUuid');
        $album = $this->albumRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($album->get($uuid), $context);
    }

    /**
     * @Route("/album.{responseFormat}", name="api.album.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->albumRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $album = $this->albumRepository->read(
            $createEvent->getAlbumUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $album,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/album.{responseFormat}", name="api.album.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->albumRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $album = $this->albumRepository->read(
            $createEvent->getAlbumUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $album,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/album.{responseFormat}", name="api.album.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->albumRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $album = $this->albumRepository->read(
            $createEvent->getAlbumUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $album,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/album/{albumUuid}.{responseFormat}", name="api.album.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('albumUuid');

        $updateEvent = $this->albumRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $album = $this->albumRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $album->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/album.{responseFormat}", name="api.album.delete", methods={"DELETE"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];

        return $this->createResponse($result, $context);
    }
}
