<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\FieldAccessorBuilder;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\PriceRulesJsonField;

class PriceRuleFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof PriceRulesJsonField) {
            return null;
        }

        $keys = $context->getRules();

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
