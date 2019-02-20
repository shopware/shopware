<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class NumberRangeController extends AbstractController
{
    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;
    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $valueGenerator;

    public function __construct(
        DefinitionRegistry $definitionRegistry,
        NumberRangeValueGeneratorInterface $valueGenerator
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->valueGenerator = $valueGenerator;
    }

    /**
     * @Route("/api/v{version}/number-range/reserve/{entity}/{saleschannel}", defaults={"saleschannel"=null}, name="api.number-range.reserve", methods={"GET"})
     */
    public function reserve(string $entity, ?string $saleschannel, Context $context): JsonResponse
    {
        $entityDefinition = $this->definitionRegistry->get($entity);
        $generatedNumber = $this->valueGenerator->getValue($entityDefinition, $context, $saleschannel);

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }
}
