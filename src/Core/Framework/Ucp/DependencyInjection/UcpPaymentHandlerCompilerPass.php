<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerRegistry;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Collects every service tagged `ucp.payment_handler` into the
 * {@see UcpPaymentHandlerRegistry}.
 *
 * @internal
 */
#[Package('framework')]
class UcpPaymentHandlerCompilerPass implements CompilerPassInterface
{
    public const TAG = 'ucp.payment_handler';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(UcpPaymentHandlerRegistry::class)) {
            return;
        }

        $registry = $container->getDefinition(UcpPaymentHandlerRegistry::class);
        $taggedServices = $container->findTaggedServiceIds(self::TAG);

        $references = [];
        foreach ($taggedServices as $id => $tags) {
            $tag = $tags[0] ?? [];
            // `name` is consumed by Symfony for the tag itself — `handler_id` carries the reverse-domain UCP id.
            $name = $tag['handler_id'] ?? null;
            if (!\is_string($name) || $name === '') {
                throw UcpException::paymentHandlerTagInvalid($id, 'missing required "handler_id" attribute');
            }

            $references[$name] = new Reference($id);
        }

        $registry->setArgument('$handlers', $references);
    }
}
