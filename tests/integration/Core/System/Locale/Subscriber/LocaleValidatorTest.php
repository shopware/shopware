<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Locale\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\Exception\InvalidLocaleCodeException;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Locale\Subscriber\LocaleValidator;

/**
 * @internal
 */
#[Package('discovery')]
class LocaleValidatorTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->localeRepository = $this->getContainer()->get('locale.repository');
        $this->definitionInstanceRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
    }

    public function testItCannotCreateLocaleWithInvalidCode(): void
    {
        try {
            $this->localeRepository->create([
                [
                    'code' => 'foo_BAR',
                    'name' => 'English',
                    'territory' => 'USA',
                ],
            ], Context::createDefaultContext());
        } catch (WriteException $e) {
            static::assertInstanceOf(InvalidLocaleCodeException::class, $e->getExceptions()[0]);
            static::assertSame(
                'Cannot create or update locale with invalid code "foo_BAR"',
                $e->getExceptions()[0]->getMessage()
            );

            return;
        }

        static::fail('WriteException not thrown');
    }

    public function testItValidatesAllDefaultLocalesWithoutErrors(): void
    {
        $locales = $this->localeRepository->search(new Criteria(), Context::createDefaultContext())->getElements();
        $definition = $this->definitionInstanceRegistry->get(LocaleDefinition::class);
        $entityExistinceMock = $this->createMock(EntityExistence::class);

        $commands = array_map(static fn (LocaleEntity $locale) => new UpdateCommand(
            $definition,
            ['code' => $locale->getCode()],
            ['id' => Uuid::fromHexToBytes($locale->getId())],
            $entityExistinceMock,
            '/0/'
        ), $locales);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            array_values($commands)
        );

        (new LocaleValidator())->preWriteValidateEvent($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }
}
