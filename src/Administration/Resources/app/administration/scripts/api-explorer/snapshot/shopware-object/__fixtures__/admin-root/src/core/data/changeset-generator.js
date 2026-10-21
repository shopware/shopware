export default class ChangesetGenerator {
    static create() {
        return new ChangesetGenerator();
    }

    /**
     * @param {object} entity
     * @returns {{changes: object, deletionQueue: Array}}
     */
    generate(entity) {
        return { changes: this.recursion(entity), deletionQueue: [] };
    }

    /**
     * @private
     */
    recursion(entity) {
        return entity;
    }

    untyped(value) {
        return value;
    }
}
