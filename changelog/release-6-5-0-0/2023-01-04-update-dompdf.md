---
title: Update DomPDF and remove custom patch version
issue: NEXT-21090
---
# Core
* Changed function `render` in `Shopware\Core\Checkout\Document\Service\PdfRenderer` to fix images are not displayed with DOMPDF with HTTPS.
* Changed some files to update CSS when upgrading Dompdf.
    * `src/Core/Framework/Resources/views/documents/style_base_portrait.css.twig`
    * `src/Core/Framework/Resources/views/documents/style_base_landscape.css.twig`
* Updated composer package `dompdf/dompdf` to version `2.0.1`.
