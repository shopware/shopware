<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Rest\ApiContext;
use Shopware\Rest\ApiController;
use Shopware\PaymentMethod\Repository\PaymentMethodRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.payment_method.api_controller", path="/api")
 */
class PaymentMethodController extends ApiController
{
    /**
     * @var PaymentMethodRepository
     */
    private $paymentMethodRepository;

    public function __construct(PaymentMethodRepository $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @Route("/paymentMethod.{responseFormat}", name="api.paymentMethod.list", methods={"GET"})
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

        $paymentMethods = $this->paymentMethodRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $paymentMethods, 'total' => $paymentMethods->getTotal()],
            $context
        );
    }

    /**
     * @Route("/paymentMethod/{paymentMethodUuid}.{responseFormat}", name="api.paymentMethod.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('paymentMethodUuid');
        $paymentMethods = $this->paymentMethodRepository->readDetail(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $paymentMethods->get($uuid)], $context);
    }

    /**
     * @Route("/paymentMethod.{responseFormat}", name="api.paymentMethod.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->paymentMethodRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $paymentMethods = $this->paymentMethodRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $paymentMethods,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/paymentMethod.{responseFormat}", name="api.paymentMethod.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->paymentMethodRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $paymentMethods = $this->paymentMethodRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $paymentMethods,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/paymentMethod.{responseFormat}", name="api.paymentMethod.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->paymentMethodRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $paymentMethods = $this->paymentMethodRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $paymentMethods,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/paymentMethod/{paymentMethodUuid}.{responseFormat}", name="api.paymentMethod.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('paymentMethodUuid');

        $updateEvent = $this->paymentMethodRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $paymentMethods = $this->paymentMethodRepository->readDetail(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $paymentMethods->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/paymentMethod.{responseFormat}", name="api.paymentMethod.delete", methods={"DELETE"})
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
        return 'paymentMethods';
    }

    protected function getXmlChildKey(): string
    {
        return 'paymentMethod';
    }
}
