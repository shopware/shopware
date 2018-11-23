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
        return item.key.name;
    });
};