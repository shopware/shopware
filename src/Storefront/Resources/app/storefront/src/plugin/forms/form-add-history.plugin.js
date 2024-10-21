import Plugin from 'src/plugin-system/plugin.class';

/**
 * @package content
 */
export default class FormAddHistoryPlugin extends Plugin {

    static options = {
        entries: [],
    };

    init() {
        this.el.addEventListener('submit', this.pushHistoryEntries.bind(this));
    }

    pushHistoryEntries() {
        this.options.entries.forEach(({ state = {}, title, url = undefined }) => {
            history.pushState(state, title, url );
        });
    }
}
