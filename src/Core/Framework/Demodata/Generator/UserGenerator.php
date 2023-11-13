<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\User\UserDefinition;

/**
 * @internal
 */
#[Package('core')]
class UserGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly UserDefinition $userDefinition,
        private readonly EntityRepository $languageRepository
    ) {
    }

    public function getDefinition(): string
    {
        return UserDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $context->getConsole()->progressStart($numberOfItems);

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $id = Uuid::randomHex();
            $firstName = $context->getFaker()->firstName();
            $lastName = $context->getFaker()->format('lastName');
            $title = $this->getRandomTitle();

            $user = [
                'id' => $id,
                'title' => $title,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'username' => $context->getFaker()->format('userName'),
                'email' => $id . $context->getFaker()->format('safeEmail'),
                'password' => 'shopware',
                'localeId' => $this->getLocaleId($context->getContext()),
            ];

            $payload[] = $user;

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->userDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->writer->upsert($this->userDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        $context->getConsole()->progressFinish();
    }

    private function getRandomTitle(): string
    {
        $titles = ['', 'Dr.', 'Dr. med.', 'Prof.', 'Prof. Dr.'];

        return $titles[array_rand($titles)];
    }

    private function getLocaleId(Context $context): string
    {
        /** @var LanguageEntity $first */
        $first = $this->languageRepository->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), $context)->first();

        return $first->getLocaleId();
    }
}
