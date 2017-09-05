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
        $responseEnvelope = $this->envelope($responseData);
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

    private function envelope($result): ResponseEnvelope
    {
        $response = new ResponseEnvelope();

        switch (true) {
            case $result instanceof Collection:
                $data = array_values(json_decode(json_encode($result->getIterator()), true));

                $response->setData($data);
                $response->setTotal($result->count());
                break;
            case $result instanceof Struct:
                $data = json_decode(json_encode($result), true);

                $response->setData($data);
                break;
        }

        return $response;
    }
}
