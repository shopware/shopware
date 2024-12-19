<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\MailerConfigurationCompilerPass;
use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Content\Mail\Transport\MailerTransportLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[CoversClass(MailerConfigurationCompilerPass::class)]
class MailerConfigurationCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mailer.default_transport', new Definition(\ArrayObject::class));
        $container->setDefinition('mailer.transports', new Definition(\ArrayObject::class));
        $container->setDefinition('mailer.mailer', new Definition(\ArrayObject::class, [null, new Reference('message_bus')]));
        $container->setDefinition(MailSender::class, new Definition(MailSender::class, [new Reference('mailer.default_transport'), new Reference('filesystem'), new Reference('config_service'), 0, new Reference('message_bus')]));

        $pass = new MailerConfigurationCompilerPass();
        $pass->process($container);

        $defaultTransport = $container->getDefinition('mailer.default_transport');

        $factory = $defaultTransport->getFactory();
        static::assertIsArray($factory);
        static::assertArrayHasKey(0, $factory);
        static::assertArrayHasKey(1, $factory);
        static::assertInstanceOf(Reference::class, $factory[0]);
        static::assertSame(MailerTransportLoader::class, (string) $factory[0]);
        static::assertSame('fromString', $factory[1]);

        $transports = $container->getDefinition('mailer.transports');

        $factory = $transports->getFactory();
        static::assertIsArray($factory);
        static::assertArrayHasKey(0, $factory);
        static::assertArrayHasKey(1, $factory);
        static::assertInstanceOf(Reference::class, $factory[0]);
        static::assertSame(MailerTransportLoader::class, (string) $factory[0]);
        static::assertSame('fromStrings', $factory[1]);

        $mailer = $container->getDefinition(MailSender::class);
        $originalMailer = $container->getDefinition('mailer.mailer');
        static::assertSame($originalMailer->getArgument(1), $mailer->getArgument(4));
    }
}
