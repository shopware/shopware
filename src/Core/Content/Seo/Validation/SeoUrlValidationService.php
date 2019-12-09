<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation;

use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class SeoUrlValidationService
{
    /**
     * @var SeoUrlRouteConfig|null
     */
    private $routeConfig;

    public function setSeoUrlRouteConfig(SeoUrlRouteConfig $config): void
    {
        $this->routeConfig = $config;
    }

    public function buildCreateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('seo_url.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function buildUpdateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('seo_url.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, Context $context): void
    {
        $fkConstraints = [new NotBlank()];

        if ($this->routeConfig) {
            $fkConstraints[] = new EntityExists([
                'entity' => $this->routeConfig->getDefinition()->getEntityName(),
                'context' => $context,
            ]);
        }

        $definition
            ->add('foreignKey', ...$fkConstraints)
            ->add('routeName', new NotBlank(), new Type('string'))
            ->add('pathInfo', new NotBlank(), new Type('string'))
            ->add('seoPathInfo', new NotBlank(), new Type('string'));
    }
}
