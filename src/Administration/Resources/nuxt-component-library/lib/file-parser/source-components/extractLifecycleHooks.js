const lifecycleHooksWhitelist = [
    'beforeCreate',
    'created',
    'beforeMount',
    'mounted',
    'beforeUpdate',
    'updated',
    'activated',
    'deactivated',
    'beforeDestroy',
    'destroyed',
    'errorCaptured',
    'beforeRouteEnter',
    'beforeRouteUpdate',
    'beforeRouteLeave'
];

module.exports = (moduleDefinition) => {
    if (!moduleDefinition) {
        return [];
    }

    return moduleDefinition.reduce((accumulator, item) => {
        if (lifecycleHooksWhitelist.includes(item.key.name)) {
            accumulator.push(item.key.name);
        }

        return accumulator;
    }, []);
};