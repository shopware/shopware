import { jsApiMigration, reference, replaceApi } from '../helpers';

export default jsApiMigration({
    id: 'js-api.extension-api-generic-cms-element-data',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Generic CMS element publishData identifiers are deprecated. Include the element id in the publishing key so multiple elements stay addressable.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        replaceApi({
            from: "Shopware.ExtensionAPI.publishData({ id: publishingKey, path: 'element' })",
            to: "Shopware.ExtensionAPI.publishData({ id: `${publishingKey}__${element.id}`, path: 'element' })",
            fix: 'unsafe-auto',
        }),
    ],
});
