<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('core')]
#[AsCommand(
    name: 'open-api:validate',
    description: 'Validates the OpenAPI schema',
)]
class OpenApiValidationCommand extends Command
{
    public function __construct(
        private HttpClientInterface $client,
        private DefinitionService $definitionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('validatorUrl', InputArgument::OPTIONAL, 'The URL of the validator', 'https://validator.swagger.io/validator/debug');
        $this->addOption('api-type', '', InputOption::VALUE_REQUIRED, 'The API type to validate', DefinitionService::API);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $validatorURL = $input->getArgument('validatorUrl');
        $apiType = match ($input->getOption('api-type')) {
            DefinitionService::API => DefinitionService::API,
            DefinitionService::STORE_API => DefinitionService::STORE_API,
            default => throw new \InvalidArgumentException('Invalid --api-type, must be one of "api" or "store-api"'),
        };

        $schema = $this->definitionService->generate(
            OpenApi3Generator::FORMAT,
            $apiType,
        );

        $response = $this->client->request('POST', $validatorURL, [
            'json' => $schema,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        $content = $response->toArray();

        // The CI validator returns an empty response if the schema is valid
        // The public Web validator returns an object with an empty (schemaValidation)Messages array
        $messages = array_merge(
            $content['messages'] ?? [],
            $content['schemaValidationMessages'] ?? []
        );

        if (\count($messages) === 0) {
            return Command::SUCCESS;
        }

        $style = new ShopwareStyle($input, $output);
        $this->renderErrorMessages($style, $messages);

        return Command::FAILURE;
    }

    /**
     * @param array<string, string|array<mixed>> $messages
     */
    private function renderErrorMessages(ShopwareStyle $style, array $messages): void
    {
        $style->error('The OpenAPI schema is invalid:');
        $table = $style->createTable();
        $table->setHeaders(['No.', 'Error']);

        foreach ($messages as $i => $message) {
            if (\is_array($message)) {
                $message = json_encode($message, \JSON_PRETTY_PRINT);
            }
            $table->addRow([$i, $message]);
        }

        $table->render();
    }
}
