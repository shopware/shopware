/**
 * @sw-package discovery
 */

// eslint-disable-next-line import/no-unresolved
import body from './body.json.twig?raw';

Shopware.Service('exportTemplateService').registerProductExportTemplate({
    name: 'open_ai',
    translationKey: 'sw-sales-channel.detail.agenticAi.templates.template-label.open-ai',
    salesChannelTypeId: Shopware.Defaults.agenticAiTypeId,
    providerName: 'open-ai',
    headerTemplate: '',
    bodyTemplate: body.trim(),
    footerTemplate: '',
    encoding: 'UTF-8',
    fileFormat: 'jsonl',
    generateByCronjob: false,
    interval: 86400,
});
