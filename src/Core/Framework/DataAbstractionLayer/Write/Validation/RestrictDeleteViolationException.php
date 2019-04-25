<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class RestrictDeleteViolationException extends ShopwareHttpException
{
    /**
     * @var RestrictDeleteViolation[]
     */
    private $restrictions;

    /**
     * @var string|EntityDefinition
     */
    private $definition;

    /**
     * @param EntityDefinition|string   $definition
     * @param RestrictDeleteViolation[] $restrictions
     */
    public function __construct(string $definition, array $restrictions)
    {
        $restriction = $restrictions[0];
        $usages = [];

        /** @var EntityDefinition|string $entityDefinition */
        /** @var string[] $ids */
        foreach ($restriction->getRestrictions() as $entityDefinition => $ids) {
            $entityDefinition = (string) $entityDefinition;
            $name = $entityDefinition::getEntityName();
            $usages[] = sprintf('%s (%d)', $name, \count($ids));
        }

        $this->restrictions = $restrictions;
        $this->definition = $definition;

        parent::__construct(
            'The delete request for {{ entity }} was denied due to a conflict. The entity is currently in use by: {{ usagesString }}',
            ['entity' => $definition::getEntityName(), 'usagesString' => implode(', ', $usages), 'usages' => $usages]
        );
    }

    /**
     * @return RestrictDeleteViolation[]
     */
    public function getRestrictions(): array
    {
        return $this->restrictions;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__DELETE_RESTRICTED';
    }
}
