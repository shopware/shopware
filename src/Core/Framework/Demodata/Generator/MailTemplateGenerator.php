<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
        int $count = 500
    ): void {
        $context->getConsole()->progressStart($count);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mail_template_type.mailTemplates.id', null));

        $mailTypeIds = $this->mailTemplateTypeRepository->search($criteria, $context->getContext())->getIds();

        $payload = [];
        foreach ($mailTypeIds as $mailTypeId => $_id) {
            $payload[] = $this->createSimpleMailTemplate($context, $mailTypeId);

            if (\count($payload) >= 10) {
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
    }

    private function createSimpleMailTemplate(DemodataContext $context, string $mailTypeId): array
    {
        $faker = $context->getFaker();
        $mailTemplate = [
            'id' => Uuid::randomHex(),
            'description' => $faker->text,
            'isSystemDefault' => false,
            'senderName' => $faker->name(),
            'subject' => $faker->text(100),
            'contentHtml' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'contentPlain' => $faker->text,
            'mailTemplateTypeId' => $mailTypeId,
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
