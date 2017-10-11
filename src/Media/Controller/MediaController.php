<?php declare(strict_types=1);

namespace Shopware\Media\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Media\Repository\MediaRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.media.api_controller", path="/api")
 */
class MediaController extends ApiController
{
    /**
     * @var MediaRepository
     */
    private $mediaRepository;

    public function __construct(MediaRepository $mediaRepository)
    {
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * @Route("/media.{responseFormat}", name="api.media.list", methods={"GET"})
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

        $media = $this->mediaRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $media, 'total' => $media->getTotal()],
            $context
        );
    }

    /**
     * @Route("/media/{mediaUuid}.{responseFormat}", name="api.media.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('mediaUuid');
        $media = $this->mediaRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $media->get($uuid)], $context);
    }

    /**
     * @Route("/media.{responseFormat}", name="api.media.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->mediaRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $media = $this->mediaRepository->read(
            $createEvent->getMediaUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $media,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/media.{responseFormat}", name="api.media.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->mediaRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $media = $this->mediaRepository->read(
            $createEvent->getMediaUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $media,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/media.{responseFormat}", name="api.media.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->mediaRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $media = $this->mediaRepository->read(
            $createEvent->getMediaUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $media,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/media/{mediaUuid}.{responseFormat}", name="api.media.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('mediaUuid');

        $updateEvent = $this->mediaRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $media = $this->mediaRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $media->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/media.{responseFormat}", name="api.media.delete", methods={"DELETE"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = ['data' => []];

        return $this->createResponse($result, $context);
    }

    protected function getXmlRootKey(): string
    {
        return 'media';
    }

    protected function getXmlChildKey(): string
    {
        return 'media';
    }
}
