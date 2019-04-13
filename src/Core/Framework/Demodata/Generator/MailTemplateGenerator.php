<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

class MailTemplateGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $writer;
    /**
     * @var MailTemplateDefinition
     */
    private $mailTemplateDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $mailTemplateTypeRepository;

    public function __construct(
        EntityWriterInterface $writer,
        EntityRepositoryInterface $mailTemplateTypeRepository,
        MailTemplateDefinition $mailTemplateDefinition
    ) {
        $this->writer = $writer;
        $this->mailTemplateTypeRepository = $mailTemplateTypeRepository;
        $this->mailTemplateDefinition = $mailTemplateDefinition;
    }

    public function getDefinition(): string
    {
        return MailTemplateDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->createMailTemplate(
            $context,
            $numberOfItems
        );
    }

    private function createMailTemplate(
        DemodataContext $context,
        $count = 500
    ): void {
        $mediaFolderId = null;
        $context->getConsole()->progressStart($count);

        $mailTypeIds = $this->mailTemplateTypeRepository->search(new Criteria(), $context->getContext())->getIds();

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $mailTemplate = $this->createSimpleMailTemplate($context, $mailTypeIds);

            $payload[] = $mailTemplate;

            if (\count($payload) >= 50) {
                $context->getConsole()->progressAdvance(\count($payload));
                $this->write($payload, $context);
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->write($payload, $context);
        }

        $context->getConsole()->progressFinish();
    }

    private function write(array $payload, DemodataContext $context): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $this->writer->upsert($this->mailTemplateDefinition, $payload, $writeContext);

        $context->add(MailTemplateDefinition::class, ...array_column($payload, 'id'));
    }

    private function createSimpleMailTemplate(DemodataContext $context, array $mailTypeIds): array
    {
        $faker = $context->getFaker();
        $mailTemplate = [
            'id' => Uuid::randomHex(),
            'description' => $faker->text,
            'isDefault' => false,
            'senderName' => $faker->name(),
            'senderMail' => $faker->safeEmail,
            'subject' => $faker->text(100),
            'contentHtml' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'contentPlain' => $faker->text,
            'mailTemplateTypeId' => \array_rand($mailTypeIds),
        ];

        return $mailTemplate;
    }

    private function generateRandomHTML(int $count, array $tags, DemodataContext $context): string
    {
        $output = '';
        for ($i = 0; $i < $count; ++$i) {
            $tag = Random::getRandomArrayElement($tags);
            $text = $context->getFaker()->words(random_int(1, 10), true);
            $output .= sprintf('<%1$s>%2$s</%1$s>', $tag, $text);
            $output .= '<br/>';
        }

        return $output;
    }
}
