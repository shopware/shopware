---
title: Add payload field to BuildValidationEvent
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added payload field to BuildValidationEvent to help to build inter-field dependant rules 
* Changed behaviour of ContactFormValidationFactory to not dispatch the BuildValidationEvent anymore
* Changed behaviour of ContactFormRoute to still dispatch the BuildValidationEvent but without the ContactFormValidationFactory
