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
    protected $restrictions;

    /**
     * @var string|EntityDefinition
     */
    protected $definition;

    /**
     * @param EntityDefinition|string   $definition
     * @param RestrictDeleteViolation[] $restrictions
     * @param int                       $code
     * @param null|\Throwable           $previous
     */
    public function __construct(string $definition, array $restrictions, $code = 0, \Throwable $previous = null)
    {
        $restriction = $restrictions[0];
        $usages = [];

        /** @var string[] $ids */
        foreach ($restriction->getRestrictions() as $entityDefinition => $ids) {
            $entityDefinition = (string) $entityDefinition;
            /** @var EntityDefinition|string $entityDefinition */
            $name = $entityDefinition::getEntityName();
            $usages[] = sprintf('%s (%d)', $name, \count($ids));
        }

        $message = sprintf(
            'The delete request for %s was denied due to a conflict. The entity is currently in use by: %s',
            $definition::getEntityName(),
            implode(', ', $usages)
        );

        parent::__construct($message, $code, $previous);

        $this->restrictions = $restrictions;
        $this->definition = $definition;
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
}
