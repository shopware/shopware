<?php

declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\SystemCheck;

use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class MailTemplateCompilationCheck extends BaseCheck
{
    private const TEMPLATES_TO_CHECK = [
        'customer_register',
        'order_confirmation_mail',
    ];

    /**
     * @internal
     *
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct(
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly EntityRepository $mailTemplateRepository,
    ) {
    }

    public function run(): Result
    {
        $context = Context::createCLIContext();

        $criteria = new Criteria();
        $criteria->addAssociation('mailTemplateType');
        $criteria->addFilter(new EqualsAnyFilter('mailTemplateType.technicalName', self::TEMPLATES_TO_CHECK));

        $mailTemplates = $this->mailTemplateRepository->search($criteria, $context)->getEntities();

        $errors = [];
        /** @var MailTemplateEntity $template */
        foreach ($mailTemplates as $template) {
            try {
                $this->templateRenderer->enableTestMode();
                $this->templateRenderer->render(
                    $template->getContentHtml() ?? '',
                    $template->getMailTemplateType()?->getTemplateData() ?? [],
                    $context
                );
                $this->templateRenderer->disableTestMode();
            } catch (\Throwable $e) {
                $errors[$template->getId()] = [
                    'type' => $template->getMailTemplateType()?->getTechnicalName() ?? 'unknown',
                    'exception' => $e->getMessage(),
                    'description' => $template->getDescription(),
                ];
            }
        }

        if (\count($errors) > 0) {
            return new Result(
                $this->name(),
                Status::FAILURE,
                'Some mail templates did not compile',
                false,
                [
                    'errors' => $errors,
                ]
            );
        }

        return new Result(
            $this->name(),
            Status::OK,
            'All important mail templates can compile',
            true,
        );
    }

    public function category(): Category
    {
        return Category::FEATURE;
    }

    public function name(): string
    {
        return 'MailTemplateCompilation';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }
}
