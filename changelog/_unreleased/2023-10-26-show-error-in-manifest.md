---
title: Enhanced error handling for invalid manifest files
issue: NEXT-31162
---

# Core

+ Added error logging to capture and track issues from invalid app manifest
+ Added clear error messages when attempting to upload an app with an invalid manifest file

* Deprecated class `\Shopware\Core\System\SystemConfig\Exception\XmlParsingException`. It will be removed, use domain specific xmlParsingExceptions instead
* Deprecated class `\Shopware\Core\System\SystemConfig\Exception\XmlElementNotFoundException`. It will be removed, use `\Shopware\Core\Framework\Util\UtilException::xmlElementNotFound` instead
* Deprecated method `\Shopware\Core\Framework\Util\XmlReader::read`. Thrown exception will change from XmlParsingException to UtilXmlParsingException
* Deprecated method `\Shopware\Core\Framework\Util\XmlReader::getElementChildValueByName`. Thrown exception will change from XmlElementNotFoundException to UtilException
* Deprecated method `\Shopware\Core\Framework\App\Flow\Action\Action::createFromXmlFile`. Thrown exception will change from XmlParsingException to AppXmlParsingException
* Deprecated method `\Shopware\Core\Framework\App\AppException::errorFlowCreateFromXmlFile`. It will be removed, use `\Shopware\Core\Framework\App\AppException::createFromXmlFileFlowError` instead
