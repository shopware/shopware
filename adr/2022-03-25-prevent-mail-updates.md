---
title: Prevent mail updates 
date: 2022-03-25
area: system-settings
tags: [mail, flow]
---
In order to guarantee an autocompletion for the different mail templates in the administration UI, we currently have a mechanism, which writes the current mail into the database when sending a mail:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

class SendMailAction extends FlowAction
{
    public function handle(Event $event): void
    {
        // ...
        
        if ($data->has('templateId')) {
            $this->updateMailTemplateType($event, $mailEvent, $mailTemplate);
        }
        
        // ...
    }

    private function updateMailTemplateType(
        FlowEvent $event, 
        MailAware $mailAware, 
        MailTemplateEntity $mailTemplate
        ): void {
        if (!$mailTemplate->getMailTemplateTypeId()) {
            return;
        }

        if (!$this->updateMailTemplate) {
            return;
        }

        $this->mailTemplateTypeRepository->update([[
            'id' => $mailTemplate->getMailTemplateTypeId(),
            'templateData' => $this->getTemplateData($mailAware),
        ]], $mailAware->getContext());
    }
}
```

This allows us to also support plugin extensions out of the box. However, the disadvantage of this mechanism is a rather high load on the database when there are many orders and registrations in the store. It creates unnecessary load due to the mail templates in the database.

To avoid this load, we have implemented the configuration `shopware.mail.update_mail_variables_on_send`, which overrides this mechanism. We recommend, to set this configuration as soon as all mail templates in the store are configured correctly. You can simply set this configuration in your `config/packages/*.yaml` file:

```yaml
shopware:
    mail:
        update_mail_variables_on_send: false
```

This is only a temporary solution. We will create an alternative for this feature in the future, which will have no impact on the database due to high order numbers.
