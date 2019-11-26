const { Mixin } = Shopware;

Mixin.register('ruleContainer', {
    inject: [
        'conditionDataProviderService',
        'createCondition',
        'insertNodeIntoTree',
        'removeNodeFromTree',
        'childAssociationField'
    ],

    props: {
        condition: {
            type: Object,
            required: true
        },

        parentCondition: {
            type: Object,
            required: false,
            default: null
        },

        level: {
            type: Number,
            required: true
        }
    },

    computed: {
        containerRowClass() {
            return this.level % 2 ? 'container-condition-level__is--odd' : 'container-condition-level__is--even';
        },

        nextPosition() {
            if (this.condition[this.childAssociationField] && this.condition[this.childAssociationField].length > 0) {
                return this.condition[this.childAssociationField].length;
            }
            return 0;
        }
    },

    watch: {
        nextPosition() {
            if (this.nextPosition === 0) {
                this.onAddPlaceholder();
            }
        }
    }
});
