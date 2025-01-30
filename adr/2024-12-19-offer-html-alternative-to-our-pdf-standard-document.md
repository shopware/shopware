---
title: "[A11y] Offer HTML alternative to our pdf standard documents"
date: 2024-12-19
area: accessibility
tags: [a11y, pdf, html]
---

## Context

To comply with Web Content Accessibility Guidelines (WCAG), we aim to make Shopware 6's document solution accessible (A11y-compliant). This ensures that our solution remains usable while meeting legal requirements and making documents accessible to customers with disabilities. 

Currently, our PDF generation library, DomPDF, does not meet accessibility standards, posing a significant challenge.

## Decision

We have decided to make HTML documents available in addition to PDF documents, as these are more accessible.

- **Better for Accessibility**: HTML is naturally organized, making it easier for accessibility tools to read and present content to people with disabilities.

- **Lack of Support**: As our current PDFs lack support for accessibility. Few tools, especially in PHP, can create accessible tagged PDFs, making it difficult to maintain PDF accessibility.

- **Industry Trends**: Many organizations are already moving from PDFs to HTML for accessibility. For example, government websites have been required to meet accessibility standards since the early 2000s. Most of them now use HTML for most of their content because it meets these standards better.

Providing HTML documents aligns with these trends and ensures we are using best practices for accessibility.

### Affected Areas

We will integrate HTML A11y document support in the following areas:

1. **Document Type Support**:
    - Support includes all document types Shopware provides by default, `invoice`, `delivery note`, `credit note`, and `cancellation invoice`. Extensions must adapt themselves.
2. **Administration**:
    - **Order Detail Page**: Option to download HTML alongside PDF for each document type.
    - **Document Settings**: Toggle to generate HTML documents.
3. **Storefront**:
    - **Order History**: Customers can access HTML and PDF versions of documents.
4. **Flow Builder**:
    - This setup requires no additional special actions, and merchants can customize file generation for "Generate documents" in the `Document Settings`
5. **Email Delivery**:
    - Enhance the original email by including a link to the HTML document. Customers will need to log in to access the document, and additional guidance will be provided.
    - We can’t attach the HTML file directly due to issues with "virus scanners", as many email providers do not allow HTML file attachments. Instead we will provide a link inside the Email.
    - A lot of the major platforms (Microsoft, Google, Amazon, etc.) will also email a summary with a link to the customer account for things like Azure/Google Cloud/etc.

### Core concept
#### Document Template

1. **Adjust Twig Template for A11y**:
    - Modify the `html.twig` templates to support accessibility (A11y) by adding elements like `tabindex` and appropriate CSS styles.

   `src/Core/Framework/Resources/views/documents/invoice.html.twig`:
    ```twig
    {% block document_headline %}
        <h1 class="headline" tabindex="0">
            <!-- Headline content -->
        </h1>
    {% endblock %}
    ```

   `src/Core/Framework/Resources/views/documents/style_base_html.css.twig`:
    ```twig
    {% block document_style_html %}
        body {
            max-width: 1200px;
            margin: auto;
            font-size: 14px;
            line-height: 18px;
        }
        ...
    {% endblock %}
    ```

2. **Metadata and Security**:
    - The generation date of the HTML will be "fingerprinted" by adding a metadata header. This allows users to track the creation date of the document.
    - Implement a Content-Security-Policy meta-tag to minimize XSS attack risks, such as disallowing JavaScript and Restricts <base> to the same domain, protecting against base URL manipulation.

   Added new Twig block for metadata`src/Core/Framework/Resources/views/documents/base.html.twig`:
    ```twig
    {% block document_head_meta_protection %}
        <meta http-equiv="Content-Security-Policy" content="script-src 'none'; base-uri 'self';">
        <meta name="date" content="{{ 'now'|date('c') }}">
    {% endblock %}
    ```

#### Core

1. **Abstract Class for Multi-Format Rendering**
    - We will introduce an abstract class, `src/Core/Checkout/Document/Service/AbstractDocumentTypeRenderer`, to support rendering multiple document types, including `PDF` and `HTML`.

    ```php
    abstract class AbstractDocumentTypeRenderer
    {
        abstract public function render(RenderedDocument $document): string;
    }
   
    class HtmlRenderer extends AbstractDocumentTypeRenderer
    {
      public function render(RenderedDocument $document): string
      {
          $content = $this->documentTemplateRenderer->render(
              ...$options
          );
          
          $document->setContentType(self::FILE_CONTENT_TYPE);
          $document->setFileExtension(self::FILE_EXTENSION);
          $document->setContent($content);
          
          return $content;
      }
    }

    class PdfRenderer extends AbstractDocumentTypeRenderer {}
    ```

2. **Service Registration**:
    - We need to use the service tag `document_type.renderer` for the `Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry` to recognize this service. This is essential for the proper registration and functioning of the `HtmlRenderer`.

    ```xml
    <service id="...\HtmlRenderer">
        <tag name="document_type.renderer" key="html"/>
    </service>
    ```
3. **Database Schema**:
    - We will add a new column `document_a11y_media_file_id` to the `document` table to store the media file ID for HTML A11y documents.

    ```sql
    ALTER TABLE `document`
    ADD COLUMN `document_a11y_media_file_id` BINARY(16);
    ```
   
    - The column is intended to link each document entry with its corresponding A11y media file `src/Core/Checkout/Document/DocumentDefinition.php`

    ```php
    (new FkField('document_a11y_media_file_id', 'documentA11yMediaFileId', MediaDefinition::class))
        ->addFlags(new ApiAware());
    ```
   
### Email Migration

- For templates that have been customized, new content must be migrated same as code below:

   `src/Core/Migration/Fixtures/mails/invoice_mail/de-plain.html.twig`
    ```twig
    {% if a11yDocuments %}
    For better accessibility, we also provide an HTML version of the documents here:

    {% for a11y in a11yDocuments %}
    {% set documentLink = rawUrl(
        'frontend.account.order.single.document.a11y',
        {
            documentId: a11y.documentId,
            deepLinkCode: a11y.deepLinkCode,
            fileType: a11y.fileExtension,
        },
        salesChannel.domains|first.url
    ) %}

        - {{ documentLink }}
    {% endfor %}
    {% endif %}
    ```

## Consequences

With this implementation, Shopware 6 will support HTML A11y documents alongside PDFs for standard document types. This change will have the following consequences:

- **Renderer Updates**: Document renderers need changes to handle HTML output, using the `AbstractDocumentTypeRenderer` [here](#core).
- **Email Integration**: For templates that have been customized, new content must be migrated as detailed in [here](#email-migration).
- **Improved Accessibility**: HTML documents make content easier to access for users with disabilities, aligning with WCAG standards.
- **Customizability**: Options in Document settings to enable or disable HTML documents should be added, giving merchants choice in document format.
