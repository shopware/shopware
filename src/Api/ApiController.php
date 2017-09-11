<?php declare(strict_types=1);

namespace Shopware\Api;

use Shopware\Api\Exception\FormatNotSupportedException;
use Shopware\Framework\Struct\Collection;
use Shopware\Framework\Struct\Struct;
use Shopware\Product\Controller\XmlResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends AbstractController
{
    abstract public function getXmlRootKey(): string;
    abstract public function getXmlChildKey(): string;

    /**
     * @param mixed      $responseData
     * @param ApiContext $context
     *
     * @return Response
     *
     * @throws FormatNotSupportedException
     */
    protected function createResponse($responseData, ApiContext $context): Response
    {
        $responseEnvelope = $this->createEnvelope($responseData);
        $responseEnvelope->setParameters($context->getParameters());

        switch ($context->getOutputFormat()) {
            case 'json':
                $response = JsonResponse::create($responseEnvelope);
                break;
            case 'xml':
                $response = XmlResponse::createXmlResponse($this->getXmlRootKey(), $this->getXmlChildKey(), $responseEnvelope);
                break;
            default:
                throw new FormatNotSupportedException($context->getOutputFormat());
        }

        // TODO -  Use paginated information
        $response->headers->set('SW-COUNT', $responseEnvelope->getTotal());
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT');
        $response->headers->set('Access-Control-Allow-Headers', 'X-Header-One,X-Header-Two');

        return $response;
    }

    private function createEnvelope($result): ResponseEnvelope
    {
        $response = new ResponseEnvelope();

        // todo: should be changed to something better than convetion
        if (is_array($result)) {
            if (array_key_exists('total', $result)) {
                $response->setTotal($result['total']);
            }

            if (array_key_exists('data', $result)) {
                $response->setData($result['data']);
            }
        }

        switch (true) {
            case $response->getData() instanceof Collection:
                $data = array_values(json_decode(json_encode($response->getData()->getIterator()), true));

                $response->setData($data);
                break;
            case $result instanceof Struct:
                $data = json_decode(json_encode($result), true);

                $response->setData($data);
                break;
            case $response->getData() instanceof Struct:
                $data = json_decode(json_encode($response->getData()), true);

                $response->setData($data);
                break;
        }

        return $response;
    }
}
