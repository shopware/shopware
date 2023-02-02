import Plugin from 'src/plugin-system/plugin.class';

/** @deprecated tag:v6.5.0 - Removed without replacement */
export default class SwagBlockLink extends Plugin {
    init() {
        this.el.addEventListener('click', (event) => {
            event.preventDefault();
        });
    }
}
