module.exports = (moduleDefinition) => {
    if (!moduleDefinition) {
        return [];
    }

    const definedMethods = moduleDefinition.reduce((accumulator, item) => {
        if (item.key.name === 'methods') {
            accumulator = item.value.properties;
        }

        return accumulator;
    }, []);
    
    return definedMethods.map((item) => {
        return item.key.name;
    });
}