/**
 * @package admin
 */

/* @private */
export {};

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Shopware.Mixin.register('ruleContainer', {
    inject: [
        'conditionDataProviderService',
        'createCondition',
        'insertNodeIntoTree',
        'removeNodeFromTree',
        'childAssociationField',
    ],

    props: {
        condition: {
            type: Object,
            required: true,
        },

        parentCondition: {
            type: Object,
            required: false,
            default: null,
        },

        level: {
            type: Number,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        containerRowClass() {
            const classes: {
                'is--disabled': boolean;
                'container-condition-level__is--odd'?: boolean;
                'container-condition-level__is--even'?: boolean;
            } = {
                'is--disabled': this.disabled,
            };

            const level = this.level % 2 ? 'container-condition-level__is--odd' : 'container-condition-level__is--even';

            classes[level] = true;

            return classes;
        },

        nextPosition() {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (this.condition[this.childAssociationField] && this.condition[this.childAssociationField].length > 0) {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-return
                return this.condition[this.childAssociationField].length;
            }
            return 0;
        },
    },

    watch: {
        nextPosition() {
            if (this.nextPosition === 0) {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.onAddPlaceholder();
            }
        },
    },
});
