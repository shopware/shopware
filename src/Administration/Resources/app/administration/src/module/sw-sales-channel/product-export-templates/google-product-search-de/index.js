/**
 * @sw-package discovery
 */

// eslint-disable-next-line import/no-unresolved
import header from './header.xml.twig?raw';
// eslint-disable-next-line import/no-unresolved
import body from './body.xml.twig?raw';
// eslint-disable-next-line import/no-unresolved
import footer from './footer.xml.twig?raw';

Shopware.Service('exportTemplateService').registerProductExportTemplate({
    name: 'google-product-search-de',
    translationKey: 'sw-sales-channel.detail.productComparison.templates.template-label.google-product-search-de',
    headerTemplate: header.trim(),
    bodyTemplate: body,
    footerTemplate: footer.trim(),
    fileName: 'google.xml',
    encoding: 'UTF-8',
    fileFormat: 'xml',
    generateByCronjob: false,
    interval: 86400,
});
