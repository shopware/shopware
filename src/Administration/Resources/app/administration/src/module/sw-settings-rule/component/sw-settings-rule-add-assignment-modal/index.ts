import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-rule-add-assignment-modal',
    load: () => import('./src/sw-settings-rule-add-assignment-modal'),
    public: {
        props: [
            'rule',
            'entityContext',
        ],
        emits: [
            'close-add-modal',
            'entities-saved',
        ],
        blocks: [
            'sw_settings_rule_add_assignment_modal',
            'sw_settings_rule_add_assignment_modal_category_tree',
            'sw_settings_rule_add_assignment_modal_listing',
            'sw_settings_rule_add_assignment_modal_footer',
            'sw_settings_rule_add_assignment_modal_cancel',
            'sw_settings_rule_add_assignment_modal_confirm',
        ],
        computed: [
            'modalSize',
        ],
        methods: [
            'createdComponent',
            'loadEntities',
            'onCloseAddModal',
            'onAdd',
            'updateEntities',
            'insertEntities',
            'onSelect',
        ],
    },
});
