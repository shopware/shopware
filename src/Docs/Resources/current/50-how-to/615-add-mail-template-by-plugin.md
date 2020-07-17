[titleEn]: <>(Add a mail template in a plugin)
[metaDescriptionEn]: <>(This HowTo will show you how you can add a custom mail template in a plugin.)
[hash]: <>(article:how_to_plugin_add_mail_template)

## Overview

With [plugins](./../20-developer-guide/10-plugin-base.md) it is possible to add custom mail templates to Shopware.

For this you only need to add the suitable entries to the database.

## Plugin Class

You can add the database entries inside your plugin install method

```php
// MyCustomMailTemplate/src/MyCustomMailTemplate.php
<?php declare(strict_types=1);

namespace MyCustomMailTemplate;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Uuid\Uuid;

class MyCustomMailTemplate extends Plugin
{
    public const TEMPLATE_TYPE_NAME = 'MyCustomType';
    public const TEMPLATE_TYPE_TECHNICAL_NAME = 'my_custom_type';
    public const MAIL_TEMPLATE_NAME = 'MyCustomMailTemplate';

    public function install(InstallContext $installContext): void
    {
        /** @var EntityRepositoryInterface $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = $this->container->get('mail_template_type.repository');
        /** @var EntityRepositoryInterface $mailTemplateRepository */
        $mailTemplateRepository = $this->container->get('mail_template.repository');
        $mailTemplateTypeId = Uuid::randomHex();
        $mailTemplateType = [
            [
                'id' => $mailTemplateTypeId,
                'name' => self::TEMPLATE_TYPE_NAME,
                'technicalName' => self::TEMPLATE_TYPE_TECHNICAL_NAME,
                'availableEntities' => [
                    'product' => 'product',
                    'salesChannel' => 'sales_channel'
                ]
            ]
        ];

        //You can add translations with the matching ISO-Codes. You always have to provide a value vor the default system language
        //Later you can change and add translations also in the administration
        $mailTemplate = [
            [
                'id' => Uuid::randomHex(),
                'mailTemplateTypeId' => $mailTemplateTypeId,
                'subject' => [
                    'en-GB' => 'Subject of my custom mail template',
                    'de-DE' => 'Betreff meiner eigenen Mailvorlage'
                    ],
                'contentPlain' => "Hello,\nthis is the content in plain text for my custom mail template\n\nKind Regards,\nYours",
                'contentHtml' => 'Hello,<br>this is the content in html for my custom mail template<br/><br/>Kind Regards,<br/>Yours',
            ]
        ];

        try {
            $mailTemplateTypeRepository->create($mailTemplateType, $installContext->getContext());
            $mailTemplateRepository->create($mailTemplate, $installContext->getContext());
        } catch (UniqueConstraintViolationException $exception) {
            // No, we've already installed the fields, it's fine.
        }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        //Keep UserData? Then do nothing here
        if ($uninstallContext->keepUserData()) {
            return;
        }

        //get the Templates and Associations added by this Plugin from the DB
        /** @var EntityRepositoryInterface $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = $this->container->get('mail_template_type.repository');
        /** @var EntityRepositoryInterface $mailTemplateRepository */
        $mailTemplateRepository = $this->container->get('mail_template.repository');

        /** @var MailTemplateTypeEntity $myCustomMailTemplateType */
        $myCustomMailTemplateType = $mailTemplateTypeRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('technicalName', self::TEMPLATE_TYPE_TECHNICAL_NAME)),
            $uninstallContext
                ->getContext()
        )->first();


        $mailTemplateIds = $mailTemplateRepository->searchIds(
            (new Criteria())
                ->addFilter(new EqualsFilter('mailTemplateTypeId', $myCustomMailTemplateType->getId())),
            $uninstallContext
                ->getContext()
        )->getIds();

        //Get the Ids from the fetched Entities
        $ids = array_map(static function ($id) {
            return ['id' => $id];
        }, $mailTemplateIds);

        //Delete the Templates which were added by this Plugin
        $mailTemplateRepository->delete($ids, $uninstallContext->getContext());

        //Delete the TemplateType which were added by this Plugin
        $mailTemplateTypeRepository->delete([
            ['id' => $myCustomMailTemplateType->getId()]
        ], $uninstallContext->getContext());
    }
}
```
