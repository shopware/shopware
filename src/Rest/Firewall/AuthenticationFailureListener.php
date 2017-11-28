<?php declare(strict_types=1);

namespace Shopware\Rest\Firewall;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthenticationFailureListener
{
    public function onAuthenticationFailureResponse(AuthenticationFailureEvent $event): void
    {
        $content = json_decode($event->getResponse()->getContent(), true);

        $event->setResponse($this->createResponse(
            $content,
            'Bad credentials, please verify that your username/password are correctly set.'
        ));
    }

    public function onJWTExpired(AuthenticationFailureEvent $event)
    {
        $content = json_decode($event->getResponse()->getContent(), true);

        $event->setResponse($this->createResponse(
            $content,
            'Your token is expired, please renew it.'
        ));
    }

    public function onJWTInvalid(AuthenticationFailureEvent $event): void
    {
        $content = json_decode($event->getResponse()->getContent(), true);

        $event->setResponse($this->createResponse(
            $content,
            'Your token is invalid, please request a new one.'
        ));
    }

    public function onJWTNotFound(AuthenticationFailureEvent $event)
    {
        $content = json_decode($event->getResponse()->getContent(), true);

        $event->setResponse($this->createResponse(
            $content,
            'Please provide a valid token.'
        ));
    }

    private function createResponse(array $originalError, string $detail): JsonResponse
    {
        $response = [
            'errors' => [
                [
                    'status' => $originalError['code'],
                    'source' => ['pointer' => ''],
                    'title' => $originalError['message'],
                    'detail' => $detail,
                ],
            ],
        ];

        return new JsonResponse($response, $originalError['code'], ['WWW-Authenticate' => 'Bearer']);
    }
}
