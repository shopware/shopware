<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller\Exception;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('storefront')]
class StorefrontException extends HttpException
{
    final public const CAN_NOT_RENDER_VIEW = 'STOREFRONT__CAN_NOT_RENDER_VIEW';
    final public const UN_SUPPORT_STOREFRONT_RESPONSE = 'STOREFRONT__UN_SUPPORT_STOREFRONT_RESPONSE';
    final public const CLASS_DONT_HAVE_TWIG_INJECTED = 'STOREFRONT__CLASS_DONT_HAVE_TWIG_INJECTED';

    /**
     * @param array<string, mixed> $parameters
     */
    public static function cannotRenderView(string $view, string $message, array $parameters): self
    {
        /**
         * The parameters array often contains large objects (like the page context). Passing them into the exception
         * message may overflow further regex functions. Therefore we filter out all objects.
         */
        $parameters = array_filter($parameters, static function ($param) {
            return !\is_object($param);
        });

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CAN_NOT_RENDER_VIEW,
            'Can not render {{ view }} view: {{ message }} with these parameters: {{ parameters }}',
            [
                'message' => $message,
                'view' => $view,
                'parameters' => \json_encode($parameters),
            ]
        );
    }

    public static function unSupportStorefrontResponse(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UN_SUPPORT_STOREFRONT_RESPONSE,
            'Symfony render implementation changed. Providing a response is no longer supported'
        );
    }

    public static function dontHaveTwigInjected(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CLASS_DONT_HAVE_TWIG_INJECTED,
            'Class {{ class }} does not have twig injected. Add to your service definition a method call to setTwig with the twig instance',
            ['class' => $class]
        );
    }
}
