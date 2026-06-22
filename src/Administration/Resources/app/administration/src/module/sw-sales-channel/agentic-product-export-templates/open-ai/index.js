/**
 * @sw-package discovery
 */
import body from './body.json.twig?raw';

Shopware.Service('exportTemplateService').registerProductExportTemplate({
    name: 'open_ai',
    translationKey: 'sw-sales-channel.detail.agenticCommerce.templates.template-label.open-ai',
    salesChannelTypeId: Shopware.Defaults.agenticCommerceTypeId,
    providerName: 'open-ai',
    headerTemplate: '',
    bodyTemplate: body.trim(),
    footerTemplate: '',
    encoding: 'UTF-8',
    fileFormat: 'jsonl',
    generateByCronjob: false,
    interval: 86400,
});
