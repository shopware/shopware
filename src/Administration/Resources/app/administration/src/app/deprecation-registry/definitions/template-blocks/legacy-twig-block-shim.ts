import { reference, replaceTemplateBlock, templateBlockMigration } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.legacy-twig-block-shim',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Legacy Twig block overrides inside Administration components are deprecated. Use sw-block with an extends attribute so overrides work with the Vue 3 block bridge.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        replaceTemplateBlock({
            from: '{% block block_name %}',
            to: '<sw-block extends="block_name">',
            fix: 'manual',
        }),
    ],
});
