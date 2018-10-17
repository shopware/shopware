<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RuleController extends Controller
{
    /**
     * @var RuleBuilder
     */
    private $ruleBuilder;

    /**
     * @var string[]
     */
    private $ruleTypes;

    public function __construct(RuleBuilder $ruleBuilder, array $ruleTypes)
    {
        $this->ruleBuilder = $ruleBuilder;
        $this->ruleTypes = $ruleTypes;
    }

    /**
     * @Route("/api/v{version}/rule/validate", name="api.rule.validate", methods={"POST"})
     */
    public function validateRule(Request $request)
    {
        $isRule = $this->ruleBuilder->isRule($request->request->all());
        echo "<pre>";
        var_dump($isRule);
        exit();
    }

    /**
     * @Route("/api/v{version}/rule_type", name="api.rule.type", methods={"GET"})
     */
    public function getRuleTypes()
    {
        $types = [];
        foreach ($this->ruleTypes as $ruleType) {
            $types[] = $ruleType::getRuleTypeDefinition();
        }

        return new JsonResponse($types);
    }
}