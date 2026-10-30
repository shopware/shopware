/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import { deprecations } from 'test/_helper_/deprecations';

const deprecatedChild = {
    name: 'sw-silence-flag-test-child',
    template: '<div class="child"><slot /></div>',
    created() {
        Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'sw-silence-flag-test-child is deprecated.');
    },
};

const otherDeprecatedChild = {
    name: 'sw-silence-flag-test-other',
    template: '<div class="other" />',
    created() {
        Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'sw-silence-flag-test-other is deprecated.');
    },
};

function mountTemplate(template, components = {}) {
    return mount({
        components: {
            'sw-silence-flag-test-child': deprecatedChild,
            'sw-silence-flag-test-other': otherDeprecatedChild,
            ...components,
        },
        template,
    });
}

describe('src/app/directive/silence-flag.directive.ts', () => {
    it('silences the deprecations of the component it sits on', () => {
        mountTemplate(`<sw-silence-flag-test-child v-silence-flag="'V6_9_0_0'" />`);

        expect(deprecations).not.toHaveTriggered('sw-silence-flag-test-child is deprecated.');
    });

    it('takes the major in the dotted spelling too', () => {
        mountTemplate(`<sw-silence-flag-test-child v-silence-flag="'v6.9.0.0'" />`);

        expect(deprecations).not.toHaveTriggered('sw-silence-flag-test-child is deprecated.');
    });

    it('keeps the deprecations of its slot content', () => {
        mountTemplate(`
            <sw-silence-flag-test-child v-silence-flag="'V6_9_0_0'">
                <sw-silence-flag-test-other />
            </sw-silence-flag-test-child>
        `);

        expect(deprecations).not.toHaveTriggered('sw-silence-flag-test-child is deprecated.');
        expect(deprecations).toHaveTriggered('sw-silence-flag-test-other is deprecated.');
    });

    it('keeps the deprecations of the components its template renders', () => {
        const parent = {
            name: 'sw-silence-flag-test-parent',
            components: { 'sw-silence-flag-test-other': otherDeprecatedChild },
            template: '<div><sw-silence-flag-test-other /></div>',
        };

        mountTemplate(`<sw-silence-flag-test-parent v-silence-flag="'V6_9_0_0'" />`, {
            'sw-silence-flag-test-parent': parent,
        });

        expect(deprecations).toHaveTriggered('sw-silence-flag-test-other is deprecated.');
    });

    it('keeps the deprecations of the component at its root', () => {
        const wrapper = {
            name: 'sw-silence-flag-test-wrapper',
            components: { 'sw-silence-flag-test-other': otherDeprecatedChild },
            template: '<sw-silence-flag-test-other />',
        };

        mountTemplate(`<sw-silence-flag-test-wrapper v-silence-flag="'V6_9_0_0'" />`, {
            'sw-silence-flag-test-wrapper': wrapper,
        });

        expect(deprecations).toHaveTriggered('sw-silence-flag-test-other is deprecated.');
    });

    it('keeps the deprecations of components without it', () => {
        mountTemplate(`
            <div>
                <sw-silence-flag-test-child v-silence-flag="'V6_9_0_0'" />
                <sw-silence-flag-test-child />
            </div>
        `);

        expect(deprecations).toHaveTriggered('sw-silence-flag-test-child is deprecated.');
    });

    it('keeps the deprecations of other majors', () => {
        mountTemplate(`<sw-silence-flag-test-child v-silence-flag="'V6_8_0_0'" />`);

        expect(deprecations).toHaveTriggered('sw-silence-flag-test-child is deprecated.');
    });

    it.activeFeatureFlags(['v6.9.0.0'])('silences nothing once the major is active', () => {
        expect(() => mountTemplate(`<sw-silence-flag-test-child v-silence-flag="'V6_9_0_0'" />`)).toThrow(
            'Tried to access deprecated functionality: sw-silence-flag-test-child is deprecated.',
        );
    });
});
