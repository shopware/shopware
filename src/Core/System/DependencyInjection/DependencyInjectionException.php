<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class DependencyInjectionException extends HttpException
{
    public const NUMBER_RANGE_REDIS_NOT_CONFIGURED = 'SYSTEM__NUMBER_RANGE_REDIS_NOT_CONFIGURED';
    public const MISSING_ENTITY_TAG_ATTRIBUTE = 'SYSTEM__MISSING_ENTITY_TAG_ATTRIBUTE';

    public static function redisNotConfiguredForNumberRangeIncrementer(): self
    {
        return new self(
            500,
            self::NUMBER_RANGE_REDIS_NOT_CONFIGURED,
            'Parameter "shopware.number_range.config.connection" is required for redis storage'
        );
    }

    public static function missingEntityTagAttribute(string $serviceId, string $tagName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_ENTITY_TAG_ATTRIBUTE,
            \sprintf('Service "%s" is tagged as "%s" but is missing the required "entity" attribute.', $serviceId, $tagName)
        );
    }

    public static function definitionNotFound(string $entity): DefinitionNotFoundException
    {
        return new DefinitionNotFoundException($entity);
    }
}
