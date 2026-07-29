<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\InvalidThemeConfigException;

#[Package('framework')]
class ThemeConfigFieldFactory
{
    public function create(string $name, array $configFieldArray): ThemeConfigField
    {
        $configField = new ThemeConfigField();
        $configField->setName($name);

        // if block can be removed, as then the method_exists check will fail pointing at the outdated config
        if (Feature::isActive('v6.8.0.0')) {
            unset($configFieldArray['label'], $configFieldArray['helpText']);
        }

        foreach ($configFieldArray as $key => $value) {
            $setter = 'set' . $key;
            if (!method_exists($configField, $setter)) {
                throw new InvalidThemeConfigException($key);
            }
            $configField->$setter($value); /* @phpstan-ignore-line */
        }

        return $configField;
    }
}
