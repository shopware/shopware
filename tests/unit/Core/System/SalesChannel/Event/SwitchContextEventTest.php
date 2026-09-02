<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\Event\SwitchContextEvent;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SwitchContextEvent::class)]
class SwitchContextEventTest extends TestCase
{
    public function testExposesTheSwitchRequest(): void
    {
        $requestData = new RequestDataBag(['currencyId' => 'currency-id']);
        $context = Generator::generateSalesChannelContext();
        $definition = new DataValidationDefinition('context_switch');

        $event = new SwitchContextEvent($requestData, $context, $definition, ['currencyId' => 'currency-id']);

        static::assertSame($requestData, $event->getRequestData());
        static::assertSame($context, $event->getSalesChannelContext());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertSame($definition, $event->getDataValidationDefinition());
        static::assertSame(['currencyId' => 'currency-id'], $event->getParameters());
    }

    public function testParametersCanBeAddedAndDeleted(): void
    {
        $event = new SwitchContextEvent(new RequestDataBag(), Generator::generateSalesChannelContext(), new DataValidationDefinition(), ['currencyId' => 'currency-id']);

        $event->addParameter('languageId', 'language-id');
        static::assertSame(['currencyId' => 'currency-id', 'languageId' => 'language-id'], $event->getParameters());

        $event->deleteParameter('currencyId');
        static::assertSame(['languageId' => 'language-id'], $event->getParameters());

        $event->deleteParameter('unknown');
        static::assertSame(['languageId' => 'language-id'], $event->getParameters());
    }
}
