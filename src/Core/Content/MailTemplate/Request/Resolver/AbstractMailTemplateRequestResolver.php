<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Request\Resolver;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

/**
 * @internal
 */
#[Package('after-sales')]
abstract readonly class AbstractMailTemplateRequestResolver
{
    /**
     * @return array<string, mixed>
     */
    protected function normalizeArrayParameter(string $parameter, mixed $value): array
    {
        if ($value instanceof DataBag) {
            return $value->all();
        }

        if (\is_array($value)) {
            return $value;
        }

        throw MailTemplateException::invalidRequestParameterType($parameter, 'array|object', get_debug_type($value));
    }

    protected function normalizeStringParameter(string $parameter, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!\is_string($value)) {
            throw MailTemplateException::invalidRequestParameterType($parameter, 'string', get_debug_type($value));
        }

        return $value;
    }

    protected function normalizeBoolParameter(string $parameter, mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return match (\is_int($value) ? $value : (\is_string($value) ? strtolower($value) : null)) {
            1, '1', 'true' => true,
            0, '0', 'false' => false,
            default => throw MailTemplateException::invalidRequestParameterType($parameter, 'bool', get_debug_type($value)),
        };
    }

    /**
     * @param array<string, mixed> $entities
     *
     * @return array<string, mixed>
     */
    protected function filterAvailableEntities(array $entities, MailTemplateEntity $mailTemplate): array
    {
        $mailTemplateType = $mailTemplate->getMailTemplateType();

        if ($mailTemplateType === null) {
            return $entities;
        }

        $availableEntities = $mailTemplateType->getAvailableEntities() ?? [];

        foreach ($entities as $key => $id) {
            if (!\array_key_exists($key, $availableEntities)) {
                unset($entities[$key]);
            }
        }

        return $entities;
    }
}
