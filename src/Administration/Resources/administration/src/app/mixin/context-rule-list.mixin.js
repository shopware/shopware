import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/contextRuleList
 */
Mixin.register('contextRuleList', {
    data() {
        return {
            contextRules: [],
            totalContextRules: 0,
            contextRuleOffset: 0,
            contextRuleLimit: 200,
            isLoadingContextRules: false
        };
    },

    mounted() {
        this.getContextRuleList();
    },

    methods: {
        getContextRuleList() {
            this.isLoadingContextRules = true;

            return this.$store.dispatch('contextRule/getContextRuleList', {
                offset: this.contextRuleOffset,
                limit: this.contextRuleLimit
            }).then((response) => {
                this.totalContextRules = response.total;
                this.contextRules = response.contextRules;
                this.isLoadingContextRules = false;

                return this.contextRules;
            });
        }
    }
});
