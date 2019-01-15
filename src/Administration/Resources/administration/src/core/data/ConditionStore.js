export default class ConditionStore {
    constructor(service) {
        this.service = service;
        this.store = service.getConditions();
        this.operators = service.operators;
        this.operatorSets = service.operatorSets;
    }

    getList() {
        return new Promise((resolve) => {
            const response = {
                items: Object.values(this.store)
            };
            resolve(response);
        });
    }

    getById(id) {
        return this.store[id];
    }

    add(name, condition) {
        if (!condition) {
            return;
        }
        this.store[name] = condition;
    }
}
