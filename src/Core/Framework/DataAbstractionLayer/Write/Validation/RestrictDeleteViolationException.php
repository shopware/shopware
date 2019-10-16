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
     * @param RestrictDeleteViolation[] $restrictions
     */
    public function __construct(EntityDefinition $definition, array $restrictions)
    {
        $restriction = $restrictions[0];
        $usages = [];

        /** @var string $entityName */
        /** @var string[] $ids */
        foreach ($restriction->getRestrictions() as $entityName => $ids) {
            $name = $entityName;
            $usages[] = sprintf('%s (%d)', $name, \count($ids));
        }

        $this->restrictions = $restrictions;

        parent::__construct(
            'The delete request for {{ entity }} was denied due to a conflict. The entity is currently in use by: {{ usagesString }}',
            ['entity' => $definition->getEntityName(), 'usagesString' => implode(', ', $usages), 'usages' => $usages]
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
