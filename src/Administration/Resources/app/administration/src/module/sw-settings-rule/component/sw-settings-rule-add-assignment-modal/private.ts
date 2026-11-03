import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
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
