---
title: Add invalid document exception to error log level notice
issue: NEXT-30170
---
# Core
* Added these following `error-codes` into the error log level `notice` of `shopware.yaml`:
  * `DOCUMENT__INVALID_DOCUMENT_ID`
  * `DOCUMENT__INVALID_GENERATOR_TYPE`
  * `DOCUMENT__ORDER_NOT_FOUND`
* Added these following exception classes into the `exception` part of `framework.yaml`:
  * `Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException`
  * `Shopware\Core\Checkout\Document\Exception\InvalidDocumentException`
  * `Shopware\Core\Checkout\Document\Exception\DocumentGenerationException`
  * `Shopware\Core\Checkout\Document\Exception\DocumentNumberAlreadyExistsException`
  * `Shopware\Core\Checkout\Document\Exception\InvalidDocumentRendererException`
  * `Shopware\Core\Checkout\Document\Exception\InvalidFileGeneratorTypeException`
