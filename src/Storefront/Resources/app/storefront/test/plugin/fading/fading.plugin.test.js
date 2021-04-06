/**
 * @jest-environment jsdom
 */
import $ from 'jquery';
import 'bootstrap';
import Fading from 'src/plugin/fading/fading.plugin.js';
import template from "./fading.template.html";

/* eslint-disable-next-line */
global.$ = global.jQuery = $;

describe('Fading plugin test', () => {
    let fadingPlugin;
    let collapseSpy;
    let linkSpy;

    let fadingEntryPoint;
    let collapse;
    let container;
    let more;
    let less;
    let eventTriggered;

    beforeEach(() => {
        document.body.innerHTML = template;
        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPluginInstances: () => {
                return [
                    {
                        refreshRegistry: () => {}
                    }
                ];
            },
            getPlugin: () => {
                return {
                    get: () => []
                };
            },
            initializePlugins: undefined
        };
        fadingEntryPoint = document.querySelector('#entryPoint');
        collapse = fadingEntryPoint.querySelector('.collapse');
        container = fadingEntryPoint.querySelector('#container');
        more = fadingEntryPoint.querySelector('#more');
        less = fadingEntryPoint.querySelector('#less');

        eventTriggered = new Promise((resolve) => {
            $(collapse).on('shown.bs.collapse', () => {
                resolve();
            });
        });

        collapseSpy = jest.spyOn(Fading.prototype, '_onCollapseShow');
        linkSpy = jest.spyOn(Fading.prototype, '_onLinkClick');
        fadingPlugin = new Fading(fadingEntryPoint);
    });
    afterEach(() => {
        fadingPlugin = null;
        collapseSpy = null;
        linkSpy = null;

        fadingEntryPoint = null;
        collapse = null;
        container = null;
        more = null;
        less = null;
    });
    test('fading plugin exists', () => {
        expect(typeof fadingPlugin).toBe('object');
    });
    test('collapse show, if scrolling is active', async () => {
        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', { configurable: true, value: 50 });
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, value: 500 });

        $(collapse).collapse('show');
        await eventTriggered;

        expect(collapseSpy).toHaveBeenCalled();
        expect(container.classList.contains('swag-fade-container')).toBe(true);
        expect(container.classList.contains('swag-fade-container-collapsed')).toBe(false);
        expect(more.classList.contains('swag-fade-link-hidden')).toBe(false);
        expect(less.classList.contains('swag-fade-link-hidden')).toBe(true);
    });
    test('collapse show, if scrolling is inactive', async () => {
        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', { configurable: true, value: 50 });
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, value: 50 });

        $(collapse).collapse('show');
        await eventTriggered;

        expect(collapseSpy).toHaveBeenCalled();
        expect(more.classList.contains('swag-fade-link-hidden')).toBe(true);
        expect(less.classList.contains('swag-fade-link-hidden')).toBe(true);
    });
    test('show more', async () => {
        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', { configurable: true, value: 50 });
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, value: 500 });

        $(collapse).collapse('show');
        await eventTriggered;

        more.dispatchEvent(new Event('click'));
        expect(linkSpy).toHaveBeenCalled();
        expect(container.classList.contains('swag-fade-container')).toBe(false);
        expect(container.classList.contains('swag-fade-container-collapsed')).toBe(true);
        expect(more.classList.contains('swag-fade-link-hidden')).toBe(true);
        expect(less.classList.contains('swag-fade-link-hidden')).toBe(false);
    });
    test('show less', async () => {
        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', { configurable: true, value: 50 });
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, value: 500 });

        $(collapse).collapse('show');
        await eventTriggered;

        less.dispatchEvent(new Event('click'));
        expect(container.classList.contains('swag-fade-container')).toBe(true);
        expect(container.classList.contains('swag-fade-container-collapsed')).toBe(false);
        expect(more.classList.contains('swag-fade-link-hidden')).toBe(false);
        expect(less.classList.contains('swag-fade-link-hidden')).toBe(true);
    });
});
