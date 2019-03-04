import SimplePlugin from './simple-plugin';

export default class ExtendedPlugin extends SimplePlugin {
    constructor(el, config, name = 'extendedPlugin') {
        super(el, config, name);
    }

    getRandomColor() {
        return '#dd4800';
    }
}
