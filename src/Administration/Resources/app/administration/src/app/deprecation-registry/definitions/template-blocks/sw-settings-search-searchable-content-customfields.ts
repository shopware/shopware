/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-search-searchable-content-customfields',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-search-searchable-content-customfields that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_search_searchable_content_customfields_state_image',
            moveTo: 'sw_settings_search_searchable_content_customfields_empty_state',
            components: ['sw-settings-search-searchable-content-customfields'],
        }),
    ],
});
