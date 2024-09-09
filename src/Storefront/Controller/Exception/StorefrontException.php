<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller\Exception;

use Shopware\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\Error as TwigError;

#[Package('storefront')]
class StorefrontException extends HttpException
{
    final public const CAN_NOT_RENDER_VIEW = 'STOREFRONT__CAN_NOT_RENDER_VIEW';
    final public const CAN_NOT_RENDER_CUSTOM_APP_VIEW = 'STOREFRONT__CAN_NOT_RENDER_CUSTOM_APP_VIEW';
    final public const UN_SUPPORT_STOREFRONT_RESPONSE = 'STOREFRONT__UN_SUPPORT_STOREFRONT_RESPONSE';
    final public const CLASS_DONT_HAVE_TWIG_INJECTED = 'STOREFRONT__CLASS_DONT_HAVE_TWIG_INJECTED';
    final public const NO_REQUEST_PROVIDED = 'STOREFRONT__NO_REQUEST_PROVIDED';
    final public const PRODUCT_REVIEW_NOT_ACTIVE = 'STOREFRONT__REVIEW_NOT_ACTIVE';

    private const CUSTOM_APP_PATH = 'custom/apps/';

    /**
     * @param array<string, mixed> $parameters
     */
    public static function renderViewException(string $view, TwigError $error, array $parameters): self
    {
        /**
         * The parameters array often contains large objects (like the page context). Passing them into the exception
         * message may overflow further regex functions. Therefore, we filter out all objects.
         */
        $parameters = array_filter($parameters, static function (mixed $param): bool {
            return !\is_object($param);
        });

        $isCustomApp = str_contains($error->getFile(), self::CUSTOM_APP_PATH);
        $errorCode = $isCustomApp ? self::CAN_NOT_RENDER_CUSTOM_APP_VIEW : self::CAN_NOT_RENDER_VIEW;

        $exception = new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $errorCode,
            'Can not render {{ view }} view: {{ message }} with these parameters: {{ parameters }}',
            [
                'message' => $error->getMessage(),
                'view' => $error->getSourceContext()?->getName() ?: $view,
                'parameters' => \json_encode($parameters) ?: '',
            ],
            $error
        );

        if ($error->getLine() !== -1) {
            $exception->line = $error->getLine();
        }
        if ($error->getFile()) {
            $exception->file = $error->getFile();
        }

        return $exception;
    }

    /**
     * @deprecated tag:v6.7.0 - Use renderViewException instead
     *
     * @param array<string, mixed> $parameters
     */
    public static function cannotRenderView(string $view, string $message, array $parameters): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(
                self::class,
                __FUNCTION__,
                'v6.7.0.0',
                'Use StorefrontException::renderViewException instead.'
            )
        );

        return self::renderViewException($view, new TwigError($message), $parameters);
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

    public static function noRequestProvided(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_REQUEST_PROVIDED,
            'No request is available.This controller action require an active request context.'
        );
    }

    /**
     * @deprecated @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function reviewNotActive(): self|ReviewNotActiveExeption
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new ReviewNotActiveExeption();
        }

        return new self(
            Response::HTTP_FORBIDDEN,
            self::PRODUCT_REVIEW_NOT_ACTIVE,
            'Reviews not activated'
        );
    }
}
