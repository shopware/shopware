<?php declare(strict_types=1);


namespace Shopware\Product\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ApiContextValueResolver implements ArgumentValueResolverInterface
{
    const DEFAULT_FORMAT = 'json';

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        return ApiContext::class === $argument->getType();
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $context = new ApiContext();
        $context->apiFormat = $this->getSupportedFormat($request->get('apiFormat', self::DEFAULT_FORMAT));

        if ($request->request->count()) {
            $context->rawData = $request->request->all();
        } elseif ($context->apiFormat === 'json') {
            $context->rawData = json_decode($request->getContent(), true);
        } elseif ($context->apiFormat === 'xml') {
            $xml = simplexml_load_string($request->getContent());
            $rawArray = json_decode(json_encode($xml), true);
            $context->rawData = $rawArray['product'];
        }

        if (!$context->rawData) {
            throw new \RuntimeException('Missing data in request');
        }


        yield $context;
    }

    /**
     * @internal
     * @param string $apiFormat
     * @return string
     */
    protected function getSupportedFormat(string $apiFormat): string
    {
        if (!in_array($apiFormat, ['xml', 'json'], true)) {
            return self::DEFAULT_FORMAT;
        }

        return $apiFormat;
    }
}
