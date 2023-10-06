<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Api;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('system-settings')]
class CustomFieldSetActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionInstanceRegistry $definitionRegistry)
    {
    }

    #[Route(path: '/api/_action/attribute-set/relations', name: 'api.action.attribute-set.get-relations', methods: ['GET'])]
    public function getAvailableRelations(): JsonResponse
    {
        $definitions = $this->definitionRegistry->getDefinitions();

        $entityNames = [];
        foreach ($definitions as $definition) {
            if (\count($definition->getFields()->filterInstance(CustomFields::class)) === 0) {
                continue;
            }
            if ($definition instanceof EntityTranslationDefinition) {
                $definition = $definition->getParentDefinition();
            }
            $entityNames[] = $definition->getEntityName();
        }
        sort($entityNames);

        return new JsonResponse($entityNames);
    }
}
