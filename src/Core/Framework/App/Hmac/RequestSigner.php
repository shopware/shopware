<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Hmac;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class RequestSigner
{
    final public const SHOPWARE_APP_SIGNATURE = 'shopware-app-signature';

    final public const SHOPWARE_SHOP_SIGNATURE = 'shopware-shop-signature';

    public function signRequest(RequestInterface $request, string $secret): RequestInterface
    {
        if ($request->getMethod() !== 'POST') {
            return clone $request;
        }

        $body = $request->getBody()->getContents();

        $request->getBody()->rewind();

        if (!\strlen($body)) {
            return clone $request;
        }

        return $request->withAddedHeader(self::SHOPWARE_SHOP_SIGNATURE, $this->signPayload($body, $secret));
    }

    public function isResponseAuthentic(ResponseInterface $response, string $secret): bool
    {
        if (!$response->hasHeader(self::SHOPWARE_APP_SIGNATURE)) {
            return false;
        }

        $responseSignature = $response->getHeaderLine(self::SHOPWARE_APP_SIGNATURE);
        $compareSignature = $this->signPayload($response->getBody()->getContents(), $secret);

        $response->getBody()->rewind();

        return hash_equals($compareSignature, $responseSignature);
    }

    public function signPayload(string $payload, string $secretKey, string $algorithm = 'sha256'): string
    {
        return hash_hmac($algorithm, $payload, $secretKey);
    }
}
