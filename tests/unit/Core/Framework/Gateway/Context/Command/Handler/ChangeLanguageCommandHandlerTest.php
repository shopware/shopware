<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\ChangeLanguageCommandHandler;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeLanguageCommandHandler::class)]
class ChangeLanguageCommandHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $command = ChangeLanguageCommand::createFromPayload(['iso' => 'de-DE']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('locale.code', 'de-DE'));

        $languageResult = new IdSearchResult(
            1,
            [['primaryKey' => 'languageId', 'data' => []]],
            $expectedCriteria,
            $context->getContext()
        );

        $languageRepo = $this->createMock(EntityRepository::class);
        $languageRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($languageResult);

        $handler = new ChangeLanguageCommandHandler($languageRepo);
        $handler->handle($command, $context, $parameters);

        static::assertSame(['languageId' => 'languageId'], $parameters);
    }

    public function testHandleWithLanguageNotFound(): void
    {
        $command = ChangeLanguageCommand::createFromPayload(['iso' => 'de-DE']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('locale.code', 'de-DE'));

        $languageResult = new IdSearchResult(
            0,
            [],
            $expectedCriteria,
            $context->getContext()
        );

        $languageRepo = $this->createMock(EntityRepository::class);
        $languageRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($languageResult);

        $this->expectExceptionObject(GatewayException::handlerException('Language with iso code {{ isoCode }} not found', ['isoCode' => 'de-DE']));

        $handler = new ChangeLanguageCommandHandler($languageRepo);
        $handler->handle($command, $context, $parameters);

        static::assertSame([], $parameters);
    }

    public function testSupportedCommands(): void
    {
        static::assertSame([ChangeLanguageCommand::class], ChangeLanguageCommandHandler::supportedCommands());
    }
}
