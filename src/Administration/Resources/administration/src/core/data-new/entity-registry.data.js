export default class EntityRegistry {
    constructor(schema) {
        this.schema = schema;
    }

    getProperties(entityName) {
        const entityProperties = this.schema[entityName].properties;
        const returnProperties = {};
        let subProperties = {};

        Object.keys(entityProperties).forEach((property) => {
            switch (entityProperties[property].type) {
                case 'json_object':
                    subProperties = entityProperties[property].properties;
                    Object.keys(subProperties).forEach((subProperty) => {
                        if (subProperties[subProperty].type === 'association') {
                            return; // Skip nested association resolving
                        }

                        returnProperties[`${property}.${subProperty}`] = subProperties[subProperty].type;
                    });
                    break;

                case 'association':
                    if (!this.schema[entityProperties[property].entity]) {
                        break;
                    }

                    subProperties = this.schema[entityProperties[property].entity].properties;
                    Object.keys(subProperties).forEach((subProperty) => {
                        if (subProperties[subProperty].type === 'association') {
                            return; // Skip nested association resolving
                        }

                        returnProperties[`${property}.${subProperty}`] = subProperties[subProperty].type;
                    });
                    break;

                default:
                    returnProperties[property] = entityProperties[property].type;
                    break;
            }
        });

        return returnProperties;
    }
}
