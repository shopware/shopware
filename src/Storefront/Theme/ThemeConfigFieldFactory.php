<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\InvalidThemeConfigException;

#[Package('storefront')]
class ThemeConfigFieldFactory
{
    public function create(string $name, array $configFieldArray): ThemeConfigField
    {
        $configField = new ThemeConfigField();
        $configField->setName($name);

        foreach ($configFieldArray as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (!method_exists($configField, $setter)) {
                throw new InvalidThemeConfigException($key);
            }
            $configField->$setter($value); // @phpstan-ignore-line
        }

        return $configField;
    }
}
