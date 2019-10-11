const DARK = 'dark';
const LIGHT = 'light';

export default class PreferedColorSchemeService {
    _mode = LIGHT;

    _storageKey = 'preferedColorScheme';

    /**
     * @return {boolean}
     */
    get isDark() {
        return this.mode === DARK;
    }

    /**
     * @return {boolean}
     */
    get isLight() {
        return this.mode === LIGHT;
    }

    /**
     * @return {string}
     */
    get mode() {
        return this._mode;
    }

    /**
     * @param value {string}
     */
    set mode(value) {
        this._mode = value;
    }

    setDark() {
        this.mode = DARK;
    }

    setLight() {
        this.mode = LIGHT;
    }

    /**
     * @return {string}
     */
    getModeFromBrowser() {
        return matchMedia('(prefers-color-scheme: dark)').matches ? DARK : LIGHT;
    }

    setFromBrowser() {
        this.set(this.getModeFromBrowser());
    }

    store() {
        localStorage.setItem(this._storageKey, this.mode);
    }

    load() {
        const value = localStorage.getItem(this._storageKey) || this.getModeFromBrowser();
        this.mode = value;
        this.store();
    }
}
