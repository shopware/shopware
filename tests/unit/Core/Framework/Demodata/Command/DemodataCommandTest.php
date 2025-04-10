<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Demodata\Command\DemodataCommand;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(DemodataCommand::class)]
class DemodataCommandTest extends TestCase
{
    private const DEFAULT_DEFINITIONS = [
        TagDefinition::class,
        RuleDefinition::class,
        MediaDefinition::class,
        CustomerDefinition::class,
        PropertyGroupDefinition::class,
        CategoryDefinition::class,
        ProductManufacturerDefinition::class,
        ProductDefinition::class,
        ProductStreamDefinition::class,
        PromotionDefinition::class,
        OrderDefinition::class,
        ProductReviewDefinition::class,
        UserDefinition::class,
        FlowDefinition::class,
        CustomFieldSetDefinition::class,
        MailTemplateDefinition::class,
        MailHeaderFooterDefinition::class,
    ];

    private EventDispatcher $dispatcher;

    private DemodataCommand $command;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->command = new DemodataCommand(
            $this->createMock(DemodataService::class),
            $this->dispatcher,
            $this->name() === 'testShowNoticeWhenNotProd' ? 'dev' : 'prod'
        );
    }

    public function testShowNoticeWhenNotProd(): void
    {
        $eventCalled = false;
        $this->dispatcher->addListener(DemodataRequestCreatedEvent::class, static function () use (&$eventCalled): void {
            $eventCalled = true;
        });

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        static::assertFalse($eventCalled, 'Event was fired.');
        static::assertStringContainsString('Demo data command should only be used in production environment.', $tester->getDisplay());
        static::assertSame(Command::INVALID, $tester->getStatusCode());
    }

    public function testRequestHasDefaults(): void
    {
        $eventCalled = false;
        $this->dispatcher->addListener(DemodataRequestCreatedEvent::class, static function (DemodataRequestCreatedEvent $event) use (&$eventCalled): void {
            $eventCalled = true;

            $items = $event->getRequest()->all();
            foreach (self::DEFAULT_DEFINITIONS as $definition) {
                static::assertArrayHasKey($definition, $items);
            }
        });

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        static::assertTrue($eventCalled, 'Event was not fired.');
        static::assertStringContainsString('Demodata Generator', $tester->getDisplay());
        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testDefaults(): void
    {
        $this->command->addOption('tags', null, InputOption::VALUE_OPTIONAL);
        $this->command->addOption('products', null, InputOption::VALUE_OPTIONAL);
        $this->command->addOption('categories', null, InputOption::VALUE_OPTIONAL);

        $this->command->addDefault('products', 1);
        $this->command->addDefault('categories', 1);

        $eventCalled = false;
        $this->dispatcher->addListener(DemodataRequestCreatedEvent::class, static function (DemodataRequestCreatedEvent $event) use (&$eventCalled): void {
            $eventCalled = true;

            static::assertSame(0, $event->getRequest()->get(TagDefinition::class));
            static::assertSame(1, $event->getRequest()->get(ProductDefinition::class));
            static::assertSame(2, $event->getRequest()->get(CategoryDefinition::class));
        });

        $tester = new CommandTester($this->command);
        $tester->execute([
            '--categories' => 2,
        ]);

        static::assertTrue($eventCalled, 'Event was not fired.');
        static::assertStringContainsString('Demodata Generator', $tester->getDisplay());
        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testResetDefaults(): void
    {
        $this->command->addOption('tags', null, InputOption::VALUE_OPTIONAL);
        $this->command->addOption('rules', null, InputOption::VALUE_OPTIONAL);
        $this->command->addOption('products', null, InputOption::VALUE_OPTIONAL);
        $this->command->addOption('categories', null, InputOption::VALUE_OPTIONAL);

        $this->command->addDefault('products', 1);
        $this->command->addDefault('categories', 1);

        $eventCalled = false;
        $this->dispatcher->addListener(DemodataRequestCreatedEvent::class, static function (DemodataRequestCreatedEvent $event) use (&$eventCalled): void {
            $eventCalled = true;

            static::assertSame(0, $event->getRequest()->get(TagDefinition::class));
            static::assertSame(5, $event->getRequest()->get(RuleDefinition::class));
            static::assertSame(0, $event->getRequest()->get(ProductDefinition::class));
            static::assertSame(2, $event->getRequest()->get(CategoryDefinition::class));
        });

        $tester = new CommandTester($this->command);
        $tester->execute([
            '--reset-defaults' => true,
            '--categories' => 2,
            '--rules' => 5,
        ]);

        static::assertTrue($eventCalled, 'Event was not fired.');
        static::assertStringContainsString('Demodata Generator', $tester->getDisplay());
        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testCustomFieldOptions(): void
    {
        $eventCalled = false;
        $this->dispatcher->addListener(DemodataRequestCreatedEvent::class, static function (DemodataRequestCreatedEvent $event) use (&$eventCalled): void {
            $eventCalled = true;

            $options = $event->getRequest()->getOptions(CustomFieldSetDefinition::class);
            static::assertArrayHasKey('relations', $options);

            $relations = $options['relations'];
            static::assertArrayHasKey('product', $relations);
            static::assertArrayHasKey('product_manufacturer', $relations);
            static::assertArrayHasKey('order', $relations);
            static::assertArrayHasKey('customer', $relations);
            static::assertArrayHasKey('media', $relations);

            static::assertSame(1, $relations['product']);
            static::assertSame(3, $relations['product_manufacturer']);
            static::assertSame(0, $relations['order']);
            static::assertSame(0, $relations['customer']);
            static::assertSame(0, $relations['media']);
        });

        $tester = new CommandTester($this->command);
        $tester->execute([
            '--product-attributes' => 1,
            '--manufacturer-attributes' => 3,
        ]);

        static::assertTrue($eventCalled, 'Event was not fired.');
        static::assertStringContainsString('Demodata Generator', $tester->getDisplay());
        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
