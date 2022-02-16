/**
 * @jest-environment jsdom
 */

/** @deprecated tag:v6.5.0 - jQuery import will be removed. */
import $ from 'jquery';

/** @deprecated tag:v6.5.0 - Unnamed import of bootstrap will be removed. */
import '../../../node_modules/bootstrap';

/** @deprecated tag:v6.5.0 - import `bootstrap5` will be renamed to `bootstrap`. */
import { Collapse } from 'bootstrap5';

import Fading from 'src/plugin/fading/fading.plugin.js';
import Feature from 'src/helper/feature.helper';
import template from './fading.template.html';

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
                        refreshRegistry: () => {},
                    },
                ];
            },
            getPlugin: () => {
                return {
                    get: () => [],
                };
            },
            initializePlugins: undefined,
        };

        /** @deprecated tag:v6.5.0 - Feature flag reset will be removed. */
        Feature.init({ 'V6_5_0_0': false });

        fadingEntryPoint = document.querySelector('#entryPoint');
        collapse = fadingEntryPoint.querySelector('.collapse');
        container = fadingEntryPoint.querySelector('#container');
        more = fadingEntryPoint.querySelector('#more');
        less = fadingEntryPoint.querySelector('#less');

        /**
         * @deprecated tag:v6.5.0 - Bootstrap v5 uses native event listeners.
         * Event will be changed to native element and native `addEventListener`:
         * collapse.addEventListener('shown.bs.collapse', () => {});
         */
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
        /** @deprecated tag:v6.5.0 - Event overwrite will be moved to `beforeEach` */
        eventTriggered = new Promise((resolve) => {
            collapse.addEventListener('shown.bs.collapse', () => {
                resolve();
            });
        });

        /** @deprecated tag:v6.5.0 - Feature flag activation will be removed. */
        Feature.init({ 'V6_5_0_0': true });

        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', { configurable: true, value: 50 });
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, value: 500 });

        new Collapse(collapse, { show: true });
        await eventTriggered;

        expect(collapseSpy).toHaveBeenCalled();
        expect(container.classList.contains('swag-fade-container')).toBe(true);
        expect(container.classList.contains('swag-fade-container-collapsed')).toBe(false);
        expect(more.classList.contains('swag-fade-link-hidden')).toBe(false);
        expect(less.classList.contains('swag-fade-link-hidden')).toBe(true);
    });

    test('collapse show, if scrolling is inactive', async () => {
        /** @deprecated tag:v6.5.0 - Event overwrite will be moved to `beforeEach` */
        eventTriggered = new Promise((resolve) => {
            collapse.addEventListener('shown.bs.collapse', () => {
                resolve();
            });
        });

        /** @deprecated tag:v6.5.0 - Feature flag activation will be removed. */
        Feature.init({ 'V6_5_0_0': true });

        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', { configurable: true, value: 50 });
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, value: 50 });

        new Collapse(collapse, { show: true });
        await eventTriggered;

        expect(collapseSpy).toHaveBeenCalled();
        expect(more.classList.contains('swag-fade-link-hidden')).toBe(true);
        expect(less.classList.contains('swag-fade-link-hidden')).toBe(true);
    });

    test('show more', async () => {
        /** @deprecated tag:v6.5.0 - Event overwrite will be moved to `beforeEach` */
        eventTriggered = new Promise((resolve) => {
            collapse.addEventListener('shown.bs.collapse', () => {
                resolve();
            });
        });

        /** @deprecated tag:v6.5.0 - Feature flag activation will be removed. */
        Feature.init({ 'V6_5_0_0': true });

        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', { configurable: true, value: 50 });
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, value: 500 });

        new Collapse(collapse, { show: true });
        await eventTriggered;

        more.dispatchEvent(new Event('click'));
        expect(linkSpy).toHaveBeenCalled();
        expect(container.classList.contains('swag-fade-container')).toBe(false);
        expect(container.classList.contains('swag-fade-container-collapsed')).toBe(true);
        expect(more.classList.contains('swag-fade-link-hidden')).toBe(true);
        expect(less.classList.contains('swag-fade-link-hidden')).toBe(false);
    });

    test('show less', async () => {
        /** @deprecated tag:v6.5.0 - Event overwrite will be moved to `beforeEach` */
        eventTriggered = new Promise((resolve) => {
            collapse.addEventListener('shown.bs.collapse', () => {
                resolve();
            });
        });

        /** @deprecated tag:v6.5.0 - Feature flag activation will be removed. */
        Feature.init({ 'V6_5_0_0': true });

        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', { configurable: true, value: 50 });
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, value: 500 });

        new Collapse(collapse, { show: true });
        await eventTriggered;

        less.dispatchEvent(new Event('click'));
        expect(container.classList.contains('swag-fade-container')).toBe(true);
        expect(container.classList.contains('swag-fade-container-collapsed')).toBe(false);
        expect(more.classList.contains('swag-fade-link-hidden')).toBe(false);
        expect(less.classList.contains('swag-fade-link-hidden')).toBe(true);
    });

    /** @deprecated tag:v6.5.0 - Test cases in `describe` block which use jQuery elements will be removed. */
    describe('fading plugin Bootstrap v4 (with jQuery)', () => {
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
});
