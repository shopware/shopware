<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class DatabaseTypeLoader extends AbstractContentElementTypeLoader
{
    public function __construct(
        private readonly ElementTypeSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly Connection $connection,
        private readonly string $environment,
    ) {
    }

    /**
     * @return list<ContentElementTypeSpecification>
     */
    public function load(): array
    {
        if ($this->environment === 'dev') {
            return [];
        }

        /** @var list<array{name: string, schema: string, app_name: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.name, t.schema, a.name as app_name
             FROM app_content_element_type t
             INNER JOIN app a ON t.app_id = a.id
             WHERE t.active = 1'
        );

        $definitions = [];

        foreach ($rows as $row) {
            $schema = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);

            $dto = $this->serializer->denormalize($schema);

            $violations = $this->validator->validate($dto);
            if ($violations->count() > 0) {
                throw ContentSystemException::elementTypeInvalid(
                    $dto->name ?: '<unknown>',
                    $violations
                );
            }

            $definitions[] = $dto->toContentElementTypeSpecification();
        }

        return $definitions;
    }
}
