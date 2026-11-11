import ShopwareComponent from 'src/component-system/component';
import { Shopware } from 'src/component-system/shopware';
import PluginManager from 'src/plugin-system/plugin.manager';
import Plugin from 'src/plugin-system/plugin.class';
import { init, refresh, reset } from 'src/dev/twig-hmr/twig-hmr';
import { NOTICE_ID } from 'src/dev/twig-hmr/notice';

/**
 * Caches its button and counts the clicks, like components that keep references to their children.
 */
class CounterComponent extends ShopwareComponent {
    static destroyed = [];

    init() {
        this.clicks = 0;
        this.button = this.el.querySelector('button');
        this.button.addEventListener('click', () => {
            this.clicks += 1;
        });
    }

    destroy() {
        CounterComponent.destroyed.push(this);
    }
}

class MarkerPlugin extends Plugin {}

/**
 * @sw-package framework
 */
describe('dev/twig-hmr with components of the component system', () => {
    let pages;

    function page(body) {
        return `<!DOCTYPE html><html><head><title>Shop</title></head><body>${body}</body></html>`;
    }

    function counter(label, inner = '') {
        return `<div class="counter" data-component="Sw:Counter"><button type="button">${label}</button>${inner}</div>`;
    }

    // The component system creates and destroys components in mutation observer callbacks and awaits the component import.
    function flushComponentSystem() {
        return new Promise((resolve) => {
            setTimeout(resolve, 0);
        });
    }

    async function load(body) {
        document.body.innerHTML = body;
        init();
        document.dispatchEvent(new Event('DOMContentLoaded'));
        await flushComponentSystem();
        await PluginManager.initializePlugins();
    }

    async function refreshAndSettle(files) {
        await refresh(files);
        await flushComponentSystem();
    }

    function counterInstance() {
        return Shopware.getComponentInstanceByElement('Sw:Counter', document.querySelector('.counter'));
    }

    beforeAll(() => {
        // jsdom has no CSS.escape, which idiomorph uses to move elements with an id.
        global.CSS ??= { escape: (value) => value };
        PluginManager.register('Marker', MarkerPlugin, '[data-marker]');
    });

    beforeEach(() => {
        pages = {};
        CounterComponent.destroyed = [];
        // Components are ES modules from the import map, which jsdom cannot load.
        jest.spyOn(Shopware, 'getComponent').mockImplementation((name) => {
            return Promise.resolve(name === 'Sw:Counter' ? CounterComponent : undefined);
        });
        global.fetch = jest.fn((url) => {
            const html = pages[url];

            return Promise.resolve({
                ok: true,
                status: 200,
                redirected: false,
                url,
                headers: { get: () => 'text/html; charset=UTF-8' },
                text: () => Promise.resolve(html),
            });
        });
        jest.spyOn(console, 'info').mockImplementation(() => {});
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(async () => {
        reset();
        document.body.innerHTML = '';
        await flushComponentSystem();
        jest.restoreAllMocks();
    });

    it('keeps an unchanged component and its state', async () => {
        await load(`<h1>Version 1</h1>${counter('Add')}`);
        const instance = counterInstance();
        instance.button.click();
        pages['http://localhost/'] = page(`<h1>Version 2</h1>${counter('Add')}`);

        await refreshAndSettle(['page.html.twig']);

        expect(document.querySelector('h1').textContent).toBe('Version 2');
        expect(counterInstance()).toBe(instance);
        expect(instance.clicks).toBe(1);
        expect(CounterComponent.destroyed).toEqual([]);
        expect(document.getElementById(NOTICE_ID)).toBeNull();
    });

    it('replaces a changed component element and creates the component again', async () => {
        await load(`<h1>Version 1</h1>${counter('Add')}`);
        const oldInstance = counterInstance();
        pages['http://localhost/'] = page(`<h1>Version 2</h1>${counter('Add to cart')}`);

        await refreshAndSettle(['components/counter.html.twig']);
        const newInstance = counterInstance();
        newInstance.button.click();

        expect(document.querySelector('.counter button').textContent).toBe('Add to cart');
        expect(newInstance).not.toBe(oldInstance);
        expect(newInstance.button).toBe(document.querySelector('.counter button'));
        expect(newInstance.clicks).toBe(1);
        expect(CounterComponent.destroyed).toEqual([oldInstance]);
        expect(Shopware.getComponentInstances('Sw:Counter')).toEqual([newInstance]);
        expect(document.getElementById(NOTICE_ID)).toBeNull();
    });

    it('replaces a changed component element with an id and keeps the id', async () => {
        const counterWithId = (label) => `<div class="counter" id="cart-counter" data-component="Sw:Counter"><button type="button">${label}</button></div>`;
        await load(`<h1>Version 1</h1>${counterWithId('Add')}`);
        const element = document.getElementById('cart-counter');
        const oldInstance = counterInstance();
        pages['http://localhost/'] = page(`<h1>Version 2</h1>${counterWithId('Add to cart')}`);

        await refreshAndSettle(['components/counter.html.twig']);

        const replaced = document.getElementById('cart-counter');
        expect(replaced).not.toBe(element);
        expect(replaced.querySelector('button').textContent).toBe('Add to cart');
        expect(counterInstance()).not.toBe(oldInstance);
        expect(CounterComponent.destroyed).toEqual([oldInstance]);
        expect(document.getElementById(NOTICE_ID)).toBeNull();
    });

    it('replaces a changed component element without the temporary ids of the components inside', async () => {
        await load(`<h1>Version 1</h1>${counter('Add', counter('Inner'))}`);
        pages['http://localhost/'] = page(`<h1>Version 2</h1>${counter('Add to cart', counter('Inner'))}`);

        await refreshAndSettle(['components/counter.html.twig']);

        expect(document.querySelector('.counter button').textContent).toBe('Add to cart');
        expect(document.querySelectorAll('.counter')).toHaveLength(2);
        document.querySelectorAll('.counter').forEach((element) => {
            expect(element.hasAttribute('id')).toBe(false);
        });
        expect(document.getElementById(NOTICE_ID)).toBeNull();
    });

    it('creates a component that the template adds and destroys one that it removes', async () => {
        await load('<main><h1>Version 1</h1></main>');
        pages['http://localhost/'] = page(`<main><h1>Version 2</h1>${counter('Add')}</main>`);

        await refreshAndSettle(['page.html.twig']);
        const instance = counterInstance();

        expect(instance).toBeInstanceOf(CounterComponent);

        pages['http://localhost/'] = page('<main><h1>Version 3</h1></main>');
        await refreshAndSettle(['page.html.twig']);

        expect(document.querySelector('.counter')).toBeNull();
        expect(CounterComponent.destroyed).toEqual([instance]);
        expect(Shopware.getComponentInstances('Sw:Counter')).toEqual([]);
        expect(document.getElementById(NOTICE_ID)).toBeNull();
    });

    it('keeps a changed component element with a plugin inside and asks for a reload', async () => {
        await load(`<h1>Version 1</h1>${counter('Add', '<span data-marker></span>')}`);
        const element = document.querySelector('.counter');
        const instance = counterInstance();
        pages['http://localhost/'] = page(`<h1>Version 2</h1>${counter('Add to cart', '<span data-marker></span>')}`);

        await refreshAndSettle(['components/counter.html.twig']);

        expect(document.querySelector('h1').textContent).toBe('Version 2');
        expect(document.querySelector('.counter')).toBe(element);
        expect(element.querySelector('button').textContent).toBe('Add');
        expect(counterInstance()).toBe(instance);
        expect(document.getElementById(NOTICE_ID).textContent).toContain('The markup of <div.counter> changed. Components: Sw:Counter. Plugins inside: Marker.');
    });
});
