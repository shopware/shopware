import header from './header.csv.twig';
import body from './body.csv.twig';

Shopware.Service('exportTemplateService').registerProductExportTemplate({
    name: 'billiger_de',
    translationKey: 'sw-sales-channel.detail.productComparison.templates.template-label.billiger-de',
    headerTemplate: header.trim(),
    bodyTemplate: body.trim(),
    footerTemplate: '',
    fileName: 'billiger.csv',
    encoding: 'UTF-8',
    fileFormat: 'csv',
    generateByCronjob: false,
    interval: 86400,
});
