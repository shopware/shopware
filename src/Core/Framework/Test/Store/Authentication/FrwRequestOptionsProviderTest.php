<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class FrwRequestOptionsProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private Context $context;

    private FrwRequestOptionsProvider $optionsProvider;

    private EntityRepository $userConfigRepository;

    public function setUp(): void
    {
        $this->context = $this->createAdminStoreContext();
        $this->optionsProvider = $this->getContainer()->get(FrwRequestOptionsProvider::class);
        $this->userConfigRepository = $this->getContainer()->get('user_config.repository');
    }

    public function testSetsFrwUserTokenIfPresentInUserConfig(): void
    {
        $frwUserToken = 'a84a653a57dc43a48ded4275524893cf';

        $this->userConfigRepository->create([
            [
                'userId' => $this->context->getSource()->getUserId(),
                'key' => FirstRunWizardClient::USER_CONFIG_KEY_FRW_USER_TOKEN,
                'value' => [
                    FirstRunWizardClient::USER_CONFIG_VALUE_FRW_USER_TOKEN => $frwUserToken,
                ],
            ],
        ], Context::createDefaultContext());

        $headers = $this->optionsProvider->getAuthenticationHeader($this->context);

        static::assertArrayHasKey('X-Shopware-Token', $headers);
        static::assertEquals($frwUserToken, $headers['X-Shopware-Token']);
    }

    public function testRemovesEmptyAuthenticationHeaderIfFrwUserTokenIsNotSet(): void
    {
        $headers = $this->optionsProvider->getAuthenticationHeader($this->context);

        static::assertEmpty($headers);
    }

    public function testThrowsInvalidContextSourceExceptionIfNotAdminApiSource(): void
    {
        static::expectException(InvalidContextSourceException::class);

        $this->optionsProvider->getAuthenticationHeader(Context::createDefaultContext());
    }
}
