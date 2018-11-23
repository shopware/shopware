module.exports = (moduleDefinition) => {
    if (!moduleDefinition) {
        return [];
    }

    const definedWatchers = moduleDefinition.reduce((accumulator, item) => {
        if (item.key.name === 'watch') {
            accumulator = item.value.properties;
        }

        return accumulator;
    }, []);
    
    return definedWatchers.map((item) => {
        return item.key.name;
    });
}