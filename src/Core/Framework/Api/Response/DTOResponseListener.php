<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * @internal
 */
#[Package('framework')]
final class DTOResponseListener
{
    public function __construct(
        private readonly StreamWriterInterface $jsonStreamWriter,
    ) {
    }

    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$result instanceof AbstractResponse) {
            return;
        }

        $json = $this->jsonStreamWriter->write(
            $result,
            Type::object($result::class),
            [
                'include_null_properties' => false,
            ]
        );
        $response = new JsonResponse((string) $json, $result->getStatusCode(), $result->getHeaders(), json: true);
        foreach ($result->getCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }

        $event->setResponse($response);
    }
}
