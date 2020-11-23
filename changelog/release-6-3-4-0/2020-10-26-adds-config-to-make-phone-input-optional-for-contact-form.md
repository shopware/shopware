---
title: Added config to make first name, last name and phone number input optional for contact form
issue: NEXT-8058
author: Claudio Bianco
author_email: info@claudio-bianco.de 
author_github: @claudiobianco
---
# Core
* Added `BuildValidationEvent` in `ContactFormValidationFactory` in order to change the validation definition via subscriber.
* Added `Shopware\Core\Migration\Migration1604499476AddDefaultSettingConfigValueForContactForm` to preserve the default behaviour that first name, last name and phone number are required. 
___
# Administration
* Added configuration for optional first name, last name and phone number in "Basic information -> Security and Privacy".
___
# Storefront
* Added check if first name, last name and and phone number input are required or optional in contact form.
