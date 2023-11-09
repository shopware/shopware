<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Twig\Node\Expression\AbstractExpression;

#[Package('checkout')]
class AdapterException extends HttpException
{
    public const UNEXPECTED_TWIG_EXPRESSION = 'FRAMEWORK__UNEXPECTED_TWIG_EXPRESSION';
    public const MISSING_EXTENDING_TWIG_TEMPLATE = 'FRAMEWORK__MISSING_EXTENDING_TWIG_TEMPLATE';
    public const TEMPLATE_SCOPE_DEFINITION_ERROR = 'FRAMEWORK__TEMPLATE_SCOPE_DEFINITION_ERROR';

    public static function unexpectedTwigExpression(AbstractExpression $expression): self
    {
        return new self(
            Response::HTTP_NOT_ACCEPTABLE,
            self::UNEXPECTED_TWIG_EXPRESSION,
            'Unexpected Expression of type "{{ type }}".',
            [
                'type' => $expression::class,
            ]
        );
    }

    public static function missingExtendsTemplate(string $template): self
    {
        return new self(
            Response::HTTP_NOT_ACCEPTABLE,
            self::MISSING_EXTENDING_TWIG_TEMPLATE,
            'Template "{{ template }}" does not have an extending template.',
            [
                'template' => $template,
            ],
        );
    }

    public static function invalidTemplateScope(mixed $scope): self
    {
        return new self(
            Response::HTTP_NOT_ACCEPTABLE,
            self::TEMPLATE_SCOPE_DEFINITION_ERROR,
            'Template scope is wronly defined: {{ scope }}',
            [
                'scope' => $scope,
            ],
        );
    }
}
