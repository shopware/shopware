<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\FieldAccessorBuilder;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Shopware\Framework\ORM\Field\ContextPricesJsonField;
use Shopware\Framework\ORM\Field\Field;

class ContextPriceFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, ApplicationContext $context, string $accessor): ?string
    {
        if (!$field instanceof ContextPricesJsonField) {
            return null;
        }

        $keys = $context->getContextRules();

        $defaultCurrencyId = Defaults::CURRENCY;
        $currencyId = $context->getCurrencyId();

        $select = [];
        foreach ($keys as $key) {
            $parsed = sprintf('`%s`.`%s`', $root, $field->getStorageName());
            $path = sprintf('$.optimized.r%s.c%s.gross', $key, $currencyId);
            $select[] = sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, "%s"))', $parsed, $path);

            if ($context->getCurrencyId() !== Defaults::CURRENCY) {
                $path = sprintf('$.optimized.r%s.c%s.gross', $key, $defaultCurrencyId);
                $select[] = sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, "%s")) * %s', $parsed, $path, $context->getCurrencyFactor());
            }
        }

        $parsed = sprintf('`%s`.`%s`', $root, 'price');
        $select[] = sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, "%s"))', $parsed, '$.gross');

        return sprintf('(CAST(COALESCE(%s) AS DECIMAL))', implode(',', $select));
    }
}
