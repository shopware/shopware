<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Exception;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - use AdapterException::renderingTemplateFailed instead - reason:remove-exception
 */
#[Package('core')]
class StringTemplateRenderingException extends AdapterException
{
    public function __construct(string $twigMessage)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__STRING_TEMPLATE_RENDERING_FAILED',
            'Failed rendering string template using Twig: {{ message }}',
            ['message' => $twigMessage]
        );
    }
}
