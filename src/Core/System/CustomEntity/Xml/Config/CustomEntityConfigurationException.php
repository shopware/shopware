<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config;

class CustomEntityConfigurationException extends \RuntimeException
{
    /**
     * @param string[] $entities
     */
    public static function entityNotGivenException(string $configFileName, array $entities): \RuntimeException
    {
        $entities = implode(', ', $entities);

        return new \RuntimeException(
            \sprintf(
                'The entities %s are not given in the entities.xml but are configured in %s',
                $entities,
                $configFileName
            )
        );
    }
}
