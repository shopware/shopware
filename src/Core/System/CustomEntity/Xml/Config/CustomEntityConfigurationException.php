<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('content')]
class CustomEntityConfigurationException extends HttpException
{
    final public const ENTITY_NOT_GIVEN_CODE = 'CUSTOM_ENTITY_NOT_GIVEN';
    final public const DUPLICATE_REFERENCES = 'CUSTOM_ENTITY_DUPLICATE_REFERENCES';
    final public const INVALID_REFERENCES = 'CUSTOM_ENTITY_INVALID_REFERENCES';

    /**
     * @param string[] $entities
     */
    public static function entityNotGiven(string $configFileName, array $entities): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ENTITY_NOT_GIVEN_CODE,
            \sprintf(
                'The entities %s are not given in the entities.xml but are configured in %s',
                implode(', ', $entities),
                $configFileName
            ),
            [
                'configFileName' => $configFileName,
                'entities' => $entities,
            ]
        );
    }

    /**
     * @param string[] $duplicates
     */
    public static function duplicateReferences(
        string $configFileName,
        string $customEntityName,
        string $xmlElement,
        array $duplicates
    ): self {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_REFERENCES,
            \sprintf(
                'In `%s`, the entity `%s` only allows unique fields per xml element, but found the following duplicates inside of `%s`: %s',
                $configFileName,
                $customEntityName,
                $xmlElement,
                \implode(', ', $duplicates)
            ),
            [
                'configFileName' => $configFileName,
                'customEntityName' => $customEntityName,
                'area' => $xmlElement,
                'duplicates' => $duplicates,
            ]
        );
    }

    /**
     * @param string[] $invalidRefs
     */
    public static function invalidReferences(
        string $configFileName,
        string $customEntityName,
        string $xmlElement,
        array $invalidRefs
    ): self {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_REFERENCES,
            \sprintf(
                'In `%s` the entity `%s` has invalid references (regarding `entities.xml`) inside of `%s`: %s',
                $configFileName,
                $customEntityName,
                $xmlElement,
                \implode(', ', $invalidRefs)
            ),
            [
                'configFileName' => $configFileName,
                'customEntityName' => $customEntityName,
                'area' => $xmlElement,
                'duplicates' => $invalidRefs,
            ]
        );
    }
}
