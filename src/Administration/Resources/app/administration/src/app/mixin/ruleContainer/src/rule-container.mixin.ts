/**
 * @sw-package framework
 */
import { defineComponent } from '../dependencies';

/**
 * @public
 *
 * Duplicated in `src/app/composables/use-rule-container`; change both together.
 */
export default defineComponent({
    /* eslint-disable sw-core-rules/mini-module-dependencies -- the condition tree provides these, they are no services with a name to import */
    inject: [
        'conditionDataProviderService',
        'createCondition',
        'insertNodeIntoTree',
        'removeNodeFromTree',
        'childAssociationField',
    ],
    /* eslint-enable sw-core-rules/mini-module-dependencies -- end of the provided names */

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
