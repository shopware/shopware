<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Api\CompanyAccountNameFieldsController;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CompanyAccountNameFieldsController::class)]
class CompanyAccountNameFieldsControllerTest extends TestCase
{
    #[DataProvider('requirementProvider')]
    public function testItAnswersForTheSalesChannel(?bool $show, ?bool $required, bool $expected): void
    {
        $controller = new CompanyAccountNameFieldsController(new CompanyAccountNameFields(new StaticSystemConfigService([
            'sales-channel-id' => [
                CompanyAccountNameFields::CONFIG_SHOW => $show,
                CompanyAccountNameFields::CONFIG_REQUIRED => $required,
            ],
        ])));

        $response = $controller->contactPersonRequired(new Request(['salesChannelId' => 'sales-channel-id']));

        static::assertSame(['contactPersonRequired' => $expected], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testItAnswersForTheShopWithoutASalesChannel(): void
    {
        $controller = new CompanyAccountNameFieldsController(new CompanyAccountNameFields(new StaticSystemConfigService([
            CompanyAccountNameFields::CONFIG_SHOW => true,
            CompanyAccountNameFields::CONFIG_REQUIRED => false,
        ])));

        $response = $controller->contactPersonRequired(new Request());

        static::assertSame(['contactPersonRequired' => false], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{bool|null, bool|null, bool}>
     */
    public static function requirementProvider(): iterable
    {
        yield 'both on' => [true, true, true];
        yield 'hidden' => [false, true, false];
        yield 'optional' => [true, false, false];
        yield 'never saved' => [null, null, true];
    }
}
