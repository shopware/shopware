---
title: Stop resolving DTDs in extension XML files
issue: https://github.com/shopware/shopware-private/issues/506
---

# Core

* Added `Shopware\Core\Framework\Util\XmlParser`, which rejects a document type declaration before libxml can resolve an external DTD or entity.
  App manifests, plugin `config.xml` files, custom entity, admin UI, flow and CMS XML files are now read through it.
  Previously the DTD was resolved before the document type was rejected, so an administrator who may upload extensions could read local files through a crafted manifest (XML external entity injection).
  It is public API, so extensions that parse XML from an untrusted source should use it instead of `XmlUtils` as well.
* Changed the exception for empty, missing or malformed extension XML files from `InvalidArgumentException` to `UtilException` (`UTIL__XML_PARSING_FAILED`, HTTP 400).
  The error messages are unchanged.
* Added the PHPStan rule `NoXmlUtilsParsingRule` to the core rule set.
  It reports direct calls to `XmlUtils::parse()` and `XmlUtils::loadFile()` outside tests.
