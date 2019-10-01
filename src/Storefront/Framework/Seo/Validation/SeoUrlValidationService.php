<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class SeoUrlValidationService implements ValidationServiceInterface
{
    /**
     * @var SeoUrlRouteConfig|null
     */
    private $routeConfig;

    public function setSeoUrlRouteConfig(SeoUrlRouteConfig $config): void
    {
        $this->routeConfig = $config;
    }

    public function buildCreateValidation(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('seo_url.create');

        $this->addConstraints($definition, $context->getContext());

        return $definition;
    }

    public function buildUpdateValidation(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('seo_url.update');

        $this->addConstraints($definition, $context->getContext());

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
