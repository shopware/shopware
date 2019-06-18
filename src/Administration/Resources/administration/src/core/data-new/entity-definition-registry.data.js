import EntityDefinition from './entity-definition.data';

export default class EntityDefinitionRegistry extends Map {
    constructor(schema) {
        if (EntityDefinitionRegistry.instance) {
            return EntityDefinitionRegistry.instance;
        }

        super();
        Object.keys(schema).forEach((definition) => {
            this.set(definition, new EntityDefinition(schema[definition]));
        });

        EntityDefinitionRegistry.instance = this;
    }

    static instance = null;
}
