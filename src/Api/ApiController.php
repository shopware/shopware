<?php declare(strict_types=1);

namespace Shopware\Api;

use Shopware\Api\Exception\FormatNotSupportedException;
use Shopware\Framework\Struct\Collection;
use Shopware\Framework\Struct\Struct;
use Shopware\Product\Controller\XmlResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    abstract public function getXmlRootKey(): string;
    abstract public function getXmlChildKey(): string;

    /**
     * @param mixed $responseData
     * @param ApiContext $context
     * @param int $statusCode
     *
     * @return Response
     * @throws FormatNotSupportedException
     */
    protected function createResponse($responseData, ApiContext $context, int $statusCode = 200): Response
    {
        $responseEnvelope = $this->createEnvelope($responseData);
        $responseEnvelope->setParameters($context->getParameters());

        switch ($context->getOutputFormat()) {
            case 'json':
                $response = JsonResponse::create($responseEnvelope, $statusCode);
                break;
            case 'xml':
                $response = XmlResponse::createXmlResponse($this->getXmlRootKey(), $this->getXmlChildKey(), $responseEnvelope, $statusCode);
                break;
            case 'profile':
                if ($this->container->getParameter('kernel.debug') !== true) {
                    throw new \RuntimeException('Profiling is only allowed in debug mode.');
                }

                $response = $this->render('@Api/profile.html.twig', ['data' => $responseEnvelope]);
                break;
            default:
                throw new FormatNotSupportedException($context->getOutputFormat());
        }

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

            if (array_key_exists('errors', $result)) {
                $response->setErrors($result['errors']);
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
