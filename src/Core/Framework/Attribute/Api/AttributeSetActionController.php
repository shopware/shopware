<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute\Api;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AttributeSetActionController extends AbstractController
{
    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;

    public function __construct(DefinitionRegistry $definitionRegistry)
    {
        $this->definitionRegistry = $definitionRegistry;
    }

    /**
     * @Route("/api/v{version}/_action/attribute-set/relations", name="api.action.attribute-set.get-relations", methods={"GET"})
     */
    public function getAvailableRelations(): JsonResponse
    {
        $definitions = $this->definitionRegistry->getElements();

        $entityNames = [];
        /** @var string|EntityDefinition $definition */
        foreach ($definitions as $definition) {
            if (count($definition::getFields()->filterInstance(AttributesField::class)) === 0) {
                continue;
            }
            if (is_subclass_of($definition, EntityTranslationDefinition::class)) {
                $definition = $definition::getParentDefinitionClass();
            }
            $entityNames[] = $definition::getEntityName();
        }
        sort($entityNames);

        return new JsonResponse($entityNames);
    }
}
