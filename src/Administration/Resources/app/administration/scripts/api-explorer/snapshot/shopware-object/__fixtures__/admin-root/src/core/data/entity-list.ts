export default class EntityList extends Array<string> {
    first(): string | undefined {
        return this[0];
    }
}
