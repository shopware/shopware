<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation;

use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @Decoratable
 */
class SeoUrlValidationFactory implements SeoUrlDataValidationFactoryInterface
{
    public function buildValidation(Context $context, ?SeoUrlRouteConfig $config): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('seo_url.create');

        $this->addConstraints($definition, $config, $context);

        return $definition;
    }

    private function addConstraints(
        DataValidationDefinition $definition,
        ?SeoUrlRouteConfig $routeConfig,
        Context $context
    ): void {
        $fkConstraints = [new NotBlank()];

        if ($routeConfig) {
            $fkConstraints[] = new EntityExists([
                'entity' => $routeConfig->getDefinition()->getEntityName(),
                'context' => $context,
            ]);
        }

        $definition
            ->add('foreignKey', ...$fkConstraints)
            ->add('routeName', new NotBlank(), new Type('string'))
            ->add('pathInfo', new NotBlank(), new Type('string'))
            ->add('seoPathInfo', new NotBlank(), new Type('string'));

        if (Feature::isActive('FEATURE_NEXT_13410')) {
            $definition
                ->add('salesChannelId', new NotBlank(), new EntityExists([
                    'entity' => SalesChannelDefinition::ENTITY_NAME,
                    'context' => $context,
                ]));
        }
    }
}
