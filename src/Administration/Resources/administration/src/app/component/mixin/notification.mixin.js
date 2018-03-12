export default {

    data() {
        return {
            limit: 5
        };
    },

    methods: {
        createAlert(alertConfig) {
            this.$emit('addAlert', alertConfig);
        }
    }

};
