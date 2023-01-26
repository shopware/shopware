---
title: Fix letter header invoice incorrect
issue: NEXT-23740
---
# Core
* Changed `Shopware\Core\Checkout\Document\Service\PdfRenderer::render` to remove `httpContext` because the problem only in SSL local.
* Changed `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig` to add config with company address.
