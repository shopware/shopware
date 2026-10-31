import PluginManager from 'src/plugin-system/plugin.manager';
import Plugin from 'src/plugin-system/plugin.class';
import QuantitySelectorPlugin from 'src/plugin/quantity-selector/quantity-selector.plugin';
import { init, refresh, reset } from 'src/dev/twig-hmr/twig-hmr';
import { NOTICE_ID } from 'src/dev/twig-hmr/notice';

/**
 * Wraps its children into a track, like the slider plugins do.
 */
class TrackPlugin extends Plugin {
    init() {
        this.clicks = 0;
        const track = document.createElement('div');
        track.className = 'track';
        track.append(...this.el.childNodes);
        this.el.append(track);
        this.el.addEventListener('click', () => {
            this.clicks += 1;
        });
    }
}

/**
 * @sw-package framework
 */
describe('dev/twig-hmr with real plugins', () => {
    let pages;

    function page(body) {
        return `<!DOCTYPE html><html><head><title>Shop</title></head><body>${body}</body></html>`;
    }

    function quantitySelector(plusId) {
        return `
            <div class="input-group" data-quantity-selector>
                <button type="button" class="btn js-btn-minus">-</button>
                <input type="number" class="form-control js-quantity-selector" name="quantity" value="1" min="1" max="10" step="1">
                <button type="button" class="btn js-btn-plus" id="${plusId}">+</button>
            </div>
        `;
    }

    function slider() {
        return '<div class="slider" data-track><div class="slide">A</div><div class="slide">B</div></div>';
    }

    async function load(body) {
        document.body.innerHTML = body;
        // The hot reload starts before the theme scripts initialize the plugins.
        init();
        await PluginManager.initializePlugins();
    }

    function clickPlus() {
        document.querySelector('.js-btn-plus').dispatchEvent(new Event('click', { bubbles: true }));
    }

    beforeAll(() => {
        // jsdom has no CSS.escape, which idiomorph uses to move elements with an id.
        global.CSS ??= { escape: (value) => value };
        PluginManager.register('QuantitySelector', QuantitySelectorPlugin, '[data-quantity-selector]');
        PluginManager.register('Track', TrackPlugin, '[data-track]');
    });

    beforeEach(() => {
        pages = {};
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

    afterEach(() => {
        reset();
        jest.restoreAllMocks();
    });

    it('keeps a working quantity selector whose markup changed and asks for a reload', async () => {
        await load(`<h1>Version 1</h1>${quantitySelector('plus-1')}`);
        const plus = document.querySelector('.js-btn-plus');
        pages['http://localhost/'] = page(`<h1>Version 2</h1>${quantitySelector('plus-2')}`);

        await refresh(['buy-widget.html.twig']);
        clickPlus();

        expect(document.querySelector('h1').textContent).toBe('Version 2');
        expect(document.querySelector('.js-btn-plus')).toBe(plus);
        expect(document.querySelector('.js-quantity-selector').value).toBe('2');
        expect(document.getElementById(NOTICE_ID).textContent).toContain('QuantitySelector');
    });

    it('keeps a plugin element without an id when the template inserts an element before its holder', async () => {
        // Whitespace matters: idiomorph soft matches the text node after the new span with the one after the holder.
        const footer = (prefix) => `<footer>
            ${prefix}
            <div class="container">
                <button type="button" class="btn">Other</button>
                ${slider()}
            </div>
            <div class="container">Second</div>
        </footer>`;
        await load(footer(''));
        const sliderElement = document.querySelector('.slider');
        const instance = PluginManager.getPluginInstanceFromElement(sliderElement, 'Track');
        pages['http://localhost/'] = page(footer('<span data-hmr-marker>Version 2</span>'));

        await refresh(['footer.html.twig']);

        expect(document.querySelector('[data-hmr-marker]').textContent).toBe('Version 2');
        expect(document.querySelector('.slider')).toBe(sliderElement);
        expect(sliderElement.hasAttribute('id')).toBe(false);
        expect(PluginManager.getPluginInstanceFromElement(sliderElement, 'Track')).toBe(instance);
        expect(document.getElementById(NOTICE_ID)).toBeNull();
    });

    it.each([
        ['changed the id of', (id) => `<main><h1>Version 2</h1><div class="slider" id="${id}-new" data-track><div class="slide">A</div><div class="slide">B</div></div></main>`],
        ['removed an ancestor of', () => '<main><h1>Version 2</h1></main>'],
    ])('keeps the page when the template %s a plugin element', async (name, next) => {
        await load('<main><h1>Version 1</h1><div class="wrapper"><div class="slider" id="gallery" data-track><div class="slide">A</div><div class="slide">B</div></div></div></main>');
        const sliderElement = document.getElementById('gallery');
        const instance = PluginManager.getPluginInstanceFromElement(sliderElement, 'Track');
        pages['http://localhost/'] = page(next('gallery'));

        await refresh(['gallery.html.twig']);

        expect(document.querySelector('h1').textContent).toBe('Version 1');
        expect(document.getElementById('gallery')).toBe(sliderElement);
        expect(PluginManager.getPluginInstanceFromElement(sliderElement, 'Track')).toBe(instance);
        expect(document.getElementById(NOTICE_ID).textContent).toContain('The template removes <div#gallery.slider>, which holds the plugins Track.');
    });

    it('never removes a plugin element when the focus changes how the morph matches elements', async () => {
        await load('<main><div><input name="search"></div><div id="kept" data-track>Plugin</div></main>');
        const sliderElement = document.getElementById('kept');
        const instance = PluginManager.getPluginInstanceFromElement(sliderElement, 'Track');
        document.querySelector('input').focus();
        pages['http://localhost/'] = page('<main><input name="search"><div id="kept" data-track>Plugin</div></main>');

        await refresh(['search.html.twig']);

        expect(sliderElement.isConnected).toBe(true);
        expect(document.getElementById('kept')).toBe(sliderElement);
        expect(PluginManager.getPluginInstanceFromElement(sliderElement, 'Track')).toBe(instance);
    });

    it('keeps unchanged plugins as they are over repeated updates', async () => {
        await load(`<h1>Version 1</h1>${quantitySelector('plus')}${slider()}`);
        const sliderElement = document.querySelector('[data-track]');
        const instance = PluginManager.getPluginInstanceFromElement(sliderElement, 'Track');

        for (const version of [2, 3, 4]) {
            pages['http://localhost/'] = page(`<h1>Version ${version}</h1>${quantitySelector('plus')}${slider()}`);

            await refresh(['heading.html.twig']);
            clickPlus();
        }
        sliderElement.dispatchEvent(new Event('click'));

        expect(document.querySelector('h1').textContent).toBe('Version 4');
        expect(document.querySelector('.js-quantity-selector').value).toBe('4');
        expect(sliderElement.querySelectorAll('.track')).toHaveLength(1);
        expect(sliderElement.querySelectorAll('.track > .slide')).toHaveLength(2);
        expect(PluginManager.getPluginInstanceFromElement(document.querySelector('[data-track]'), 'Track')).toBe(instance);
        expect(instance.clicks).toBe(1);
        expect(document.getElementById(NOTICE_ID)).toBeNull();
    });

    it('leaves plugin elements inside markup that a script added to the script', async () => {
        await load('<main><h1>Version 1</h1></main>');
        document.querySelector('main').insertAdjacentHTML('beforeend', `<div class="script-root">${slider()}</div>`);
        await PluginManager.initializePlugins();
        const sliderElement = document.querySelector('[data-track]');
        pages['http://localhost/'] = page('<main><h1>Version 2</h1></main>');

        await refresh(['heading.html.twig']);

        expect(document.querySelector('h1').textContent).toBe('Version 2');
        expect(document.querySelector('[data-track]')).toBe(sliderElement);
        expect(sliderElement.querySelectorAll('.track')).toHaveLength(1);
        expect(document.getElementById(NOTICE_ID)).toBeNull();
    });

    it('initializes a plugin that the template added', async () => {
        await load('<h1>Version 1</h1>');
        pages['http://localhost/'] = page(`<h1>Version 2</h1>${slider()}`);

        await refresh(['slider.html.twig']);

        const sliderElement = document.querySelector('[data-track]');
        expect(sliderElement.querySelectorAll('.track > .slide')).toHaveLength(2);
        expect(document.getElementById(NOTICE_ID)).toBeNull();
    });
});
