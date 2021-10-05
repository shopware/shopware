---
title: Correct copy-paste variables function 
issue: NEXT-16077
---
# Core
* Added new function `getAt` in `Shopware\Core\Framework\Struct\Collection` to get the correct position element in the collection
* Changed function `send` in `Shopware\Core\Content\MailTemplate\Api\MailActionController` to add more the template data for send mail on test mode
* Changed function `send` in `Shopware\Core\Content\Mail\Service\MailService` to correct the send mail feature in the test mode
___
# Administration
* Added new function `mailTemplateReplace` in `sw-mail-template/page/sw-mail-template-detail/index.js` to replace the html content to suitable with mail template data
* Changed the function `loadAvailableVariables` in `sw-mail-template/page/sw-mail-template-detail/index.js` to correct the value for copy-paste variables function 
* Changed the function `getItems` in `tree/sw-tree-item/index.js` to correct the value for copy-paste variables function
* Changed the function `getTreeItems` in `tree/sw-tree/index.js` to correct the value for copy-paste variables function
