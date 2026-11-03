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
    name: 'sw-condition-modal',
    load: () => import('./src/sw-condition-modal'),
    public: {
        props: [
            'conditionDataProviderService',
            'condition',
            'scopes',
            'allowedTypes',
            'childAssociationField',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_condition_modal',
            'sw_condition_modal_tree',
            'sw_condition_modal_footer',
            'sw_condition_modal_footer_cancel',
            'sw_condition_modal_footer_save',
        ],
        computed: [
            'conditionRepository',
            'initialConditions',
        ],
        methods: [
            'onConditionsChanged',
            'deleteAndClose',
            'saveAndCloseModal',
            'deleteChildren',
            'closeModal',
        ],
    },
});
