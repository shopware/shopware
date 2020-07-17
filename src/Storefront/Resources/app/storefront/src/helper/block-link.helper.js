import Plugin from 'src/plugin-system/plugin.class';

export default class SwagBlockLink extends Plugin {
    init() {
        this.el.addEventListener('click', (event) => {
            event.preventDefault();
        });
    }
}
