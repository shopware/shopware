import NavbarPlugin from 'src/plugin/navbar/navbar.plugin';

/**
 * What a theme would ship: a subclass of a core storefront plugin in its own module,
 * so `() => import(...)` resolves a genuinely separate module.
 */
export default class NavbarOverridePlugin extends NavbarPlugin {
    _marker() {
        return 'override-ran';
    }

    init() {
        super.init();
        this.el.setAttribute('data-override-init', 'true');
    }
}
