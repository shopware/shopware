---
title: New document type e-invoice
issue: NEXT-38766
author: Fabian Boensch
author_github: @En0Ma1259
---
# Core
* Added possibility to the `Shopware\Core\Checkout\Document\Service\DocumentGenerator` to render other document formats apart from `PDF`
* Added new `finalize` method to `Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer`
* Added parameter `html` to `render` method in `Shopware\Core\Checkout\Document\Service\PdfRenderer` and will be required in 6.7.0.0
* Deprecated `html` property and `getHtml` method in `Shopware\Core\Checkout\Document\Renderer\RenderedDocument` and will be removed in 6.7.0.0
* Deprecated `fileType` property `Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation` and will be removed in 6.7.0.0
* Deprecated `Shopware\Core\Checkout\Document\Service\PdfRenderer::render()` call in `Shopware\Core\Checkout\Document\Service\DocumentGenerator`. If needed, `PdfRenderer::render()` must be called in each AbstractDocumentRenderer separately for 6.7.0.0
* Deprecated `finalize` method in `Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer` and will be removed in 6.7.0.0
* Added new document types for e-invoices. `zugferd_invoice` and `zugferd_embedded_invoice`
___
# Upgrade Information
## AbstractDocumentRenderer render workflow
With the next major version the PDF rendering will be moved from the `Shopware\Core\Checkout\Document\Service\DocumentGenerator` to each renderer with a PDF document.
Each implementation of the `Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer` class needs to set the fully rendered file with `\Shopware\Core\Checkout\Document\Renderer\RenderedDocument::setContent()`.
With this change the `Shopware\Core\Checkout\Document\Renderer\RenderedDocument::html` property is not needed anymore and will be removed.
Since every renderer can only delivery one specific filetype and the `Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation::fileType` will not be validated, this property will be removed and each renderer has to set the `Shopware\Core\Checkout\Document\Renderer\RenderedDocument::fileExtension` by themselves.

If you want to be forward compatible, render your PDF document within your renderer and use a const value for the `fileExtension`.
```php
$doc = new RenderedDocument(
    $html, // @deprecated html property will be removed 
    $number,
    $config->buildName(),
    FileTypes::PDF,
    $config->jsonSerialize(),
);
if (Feature::isActive('v6.7.0.0')) {
    $doc->setContent($this->pdfRenderer->render($doc, $html));
}
```
___
# Next Major Version Changes
The `Shopware\Core\Checkout\Document\Service\PdfRenderer::render` call was moved from `Shopware\Core\Checkout\Document\Service\DocumentGenerator` into each implementation of `Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer` with a resulting PDF document.
`Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer::finalize` method was also removed, which was added to modify a rendered document.

Before:
```php
// e.g. InvoiceRenderer
$doc = new RenderedDocument(
    $html,
    $number,
    $config->buildName(),
    $operation->getFileType(),
    $config->jsonSerialize(),
);
```
After:
```php
// e.g. InvoiceRenderer
$doc = new RenderedDocument(
    $number,
    $config->buildName(),
    FileTypes::PDF,
    $config->jsonSerialize(),
);
$doc->setContent($this->pdfRenderer->render($doc, $html));
```

The following properties will be **removed in 6.7**
* `Shopware\Core\Checkout\Document\Renderer\RenderedDocument::html`
* `Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation::fileType`
Before:
```php
// RenderedDocument
public function __construct(
    private readonly string $html = '',
    private readonly string $number = '',
    private string $name = '',
    private readonly string $fileExtension = FileTypes::PDF,
    private readonly array $config = [],
    private ?string $contentType = 'application/pdf',
    private string $content = ''
) {
}

// DocumentGenerateOperation
public function __construct(
    protected string $orderId,
    protected string $fileType = FileTypes::PDF,
    protected array $config = [],
    protected ?string $referencedDocumentId = null,
    protected bool $static = false,
    protected bool $preview = false
) {
}
```
After:
```php
// RenderedDocument
public function __construct(
    private readonly string $number = '',
    private string $name = '',
    private readonly string $fileExtension = FileTypes::PDF,
    private readonly array $config = [],
    private ?string $contentType = 'application/pdf',
    private string $content = ''
) {
}

// DocumentGenerateOperation
public function __construct(
    protected string $orderId,
    protected array $config = [],
    protected ?string $referencedDocumentId = null,
    protected bool $static = false,
    protected bool $preview = false
) {
}
```
