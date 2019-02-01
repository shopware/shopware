import Emitter from "./emitter.helper";

export default class Plugin extends Emitter {
    constructor(name) {
        super();
        this._name = name;

        window.setTimeout(() => {
            this.init();
        }, 0);
    }

    init() {
        this.trigger(`initialized`);
    }

    get name() {
        return this._name;
    }

    set name(val) {
        this._name = val;
    }
}