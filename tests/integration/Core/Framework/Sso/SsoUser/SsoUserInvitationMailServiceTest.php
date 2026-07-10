<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Sso\SsoUser;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
#[Group('slow')]
class SsoUserInvitationMailServiceTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private string $localeId;

    private SsoUserInvitationMailService $ssoUserInvitationMailService;

    protected function setUp(): void
    {
        parent::setUp();

        $localeRepository = static::getContainer()->get('locale.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', 'pl-PL'));
        $locale = $localeRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(LocaleEntity::class, $locale);

        $this->localeId = $locale->getId();

        $this->getContainer()->get('language.repository')->create(
            [[
                'id' => Uuid::randomHex(),
                'name' => 'pl-PL',
                'localeId' => $this->localeId,
                'parentId' => Defaults::LANGUAGE_SYSTEM,
                'active' => true,
                'salesChannels' => [
                    ['id' => TestDefaults::SALES_CHANNEL],
                ],
                'salesChannelDefaultAssignments' => [
                    ['id' => TestDefaults::SALES_CHANNEL],
                ],
            ]],
            Context::createDefaultContext()
        );

        $this->ssoUserInvitationMailService = static::getContainer()->get(SsoUserInvitationMailService::class);
    }

    public function testSendInvitationMailToUser(): void
    {
        $source = new AdminApiSource(null, null);
        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $caughtEvent = null;
        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            MailBeforeSentEvent::class,
            static function (MailBeforeSentEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->ssoUserInvitationMailService->sendInvitationMailToUser(
            'foo@bar.baz',
            $this->localeId,
            $context
        );

        static::assertInstanceOf(MailBeforeSentEvent::class, $caughtEvent);
        static::assertSame('Administrator invited you to join Demostore', $caughtEvent->getData()['subject']);
        static::assertNull($caughtEvent->getData()['senderEmail']);
    }
}
