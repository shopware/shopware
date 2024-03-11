<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Command\CreateIntegrationCommand;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Dotenv;

/**
 * @internal
 */
#[CoversClass(CreateIntegrationCommand::class)]
class CreateIntegrationCommandTest extends TestCase
{
    /**
     * @return array<array<bool>>
     */
    public static function createIntegrationDataProvider(): array
    {
        return [
            ['admin' => false],
            ['admin' => true],
        ];
    }

    #[DataProvider('createIntegrationDataProvider')]
    public function testCreateIntegration(bool $adminOption): void
    {
        $integrationRepository = $this->createMock(EntityRepository::class);

        $accessKey = null;
        $secretAccessKey = null;
        $admin = null;
        $integrationRepository->expects(static::once())
            ->method('create')
            ->with(static::callback(function ($input) use (&$accessKey, &$secretAccessKey, &$admin) {
                $accessKey = $input[0]['accessKey'];
                $secretAccessKey = $input[0]['secretAccessKey'];
                $admin = $input[0]['admin'];

                return true;
            }), static::anything());

        $cmd = new CommandTester(new CreateIntegrationCommand($integrationRepository));
        $parameters = ['name' => 'Test'];
        if ($adminOption) {
            $parameters['--admin'] = true;
        }
        $cmd->execute($parameters);

        $cmd->assertCommandIsSuccessful();

        static::assertNotNull($accessKey);
        static::assertNotNull($secretAccessKey);
        static::assertNotNull($admin);
        static::assertSame((bool) $adminOption, $admin);

        $output = $cmd->getDisplay();
        static::assertNotEmpty($output);

        $parsedEnv = (new Dotenv())->parse($output);
        static::assertCount(2, $parsedEnv);
        static::assertSame($accessKey, $parsedEnv['SHOPWARE_ACCESS_KEY_ID']);
        static::assertSame($secretAccessKey, $parsedEnv['SHOPWARE_SECRET_ACCESS_KEY']);
    }
}
