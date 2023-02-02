[titleEn]: <>(Setup mail templates and mail configuration)
[metaDescriptionEn]: <>(This HowTo will give an example on setting up the mail templates and the mail configuration.)
[hash]: <>(article:how_to_mail_templates)

## Overview

Shopware 6 sends different emails on different purposes. E.g for the registration of a customer or as an order confirmation. To prevent your system from accidentally sending mails while it is not live, no mail template is assigned to a sales channel by default.

## Setup

First you need to make sure that you have a correct MAILER_URL set. This has been configured during the setup process and is stored in the .env file in the root directory.
Then you need to setup your email address for outgoing mails in the administration (Settings->Basic information->Shop owner email address).
After that the mail template which should be sent have to be assigned to the sales channel. (Settings->Email templates->_Template_->edit->Sales Channels)

Now your shop is set up to send mails for the given events.

## Change email templates
You can change any Email template in the administration as well. Just go to the Email templates (Settings->Email templates->_Template_->edit) and change the content of the templates in the _plain_ and _HTML_ boxes.
