module.exports = (moduleDefinition) => {
    if (!moduleDefinition) {
        return [];
    }

    const definedComputed = moduleDefinition.reduce((accumulator, item) => {
        if (item.key.name === 'computed') {
            accumulator = item.value.properties;
        }

        return accumulator;
    }, []);

    return definedComputed.map((item) => {
        if (!item.key) {
            return;
        }

        return item.key.name;
    });
};
