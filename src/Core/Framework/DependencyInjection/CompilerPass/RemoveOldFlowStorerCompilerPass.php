<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Content\Flow\Dispatching\Storer\ConfirmUrlStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ContactFormDataStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ContentsStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ContextTokenStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\DataStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\EmailStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\NameStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\RecipientsStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ResetUrlStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ReviewFormDataStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ShopNameStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\SubjectStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\TemplateDataStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\UrlStorer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('business-ops')]
class RemoveOldFlowStorerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $deprecated = [
            ResetUrlStorer::class,
            RecipientsStorer::class,
            ContextTokenStorer::class,
            NameStorer::class,
            DataStorer::class,
            ContactFormDataStorer::class,
            ContentsStorer::class,
            ConfirmUrlStorer::class,
            ReviewFormDataStorer::class,
            EmailStorer::class,
            UrlStorer::class,
            TemplateDataStorer::class,
            SubjectStorer::class,
            ShopNameStorer::class,
        ];

        foreach ($deprecated as $serviceId) {
            $this->removeTag($container, $serviceId);
        }
    }

    private function removeTag(ContainerBuilder $container, string $serviceId): void
    {
        if (!$container->hasDefinition($serviceId)) {
            return;
        }

        $definition = $container->getDefinition($serviceId);

        if (!$definition->hasTag('flow.storer')) {
            return;
        }

        $definition->clearTag('flow.storer');
    }
}
