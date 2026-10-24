/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { change, component, compareResponse, objectNode, snapshot } from '../__fixtures__/compare-fixture';
import { ComponentsView } from './components-view';

function publicToPrivateFixture() {
    const onSave = {
        visibility: 'public' as const,
        deprecation: null,
        location: null,
        name: 'onSave',
        parameters: [],
        returnType: 'void',
        async: false,
        typeSource: 'typescript' as const,
    };
    const base = snapshot('trunk', objectNode('Shopware', 'object'), [
        component('sw-product-detail', { methods: [onSave] }),
        component('sw-button-process', { visibility: 'private' }),
        component('sw-data-grid', { visibility: 'private' }),
    ]);
    const head = snapshot('WORKTREE', objectNode('Shopware', 'object'), [
        component('sw-product-detail', { visibility: 'private' }),
        component('sw-button-process', { visibility: 'private' }),
        component('sw-data-grid', { visibility: 'private' }),
    ]);

    return compareResponse(base, head, [
        change('breaking', 'method', 'sw-product-detail', 'onSave', { change: 'removed' }),
        change('potentiallyBreaking', 'component', 'sw-product-detail', 'sw-product-detail', {
            reasons: ['Visibility changed from public to private.'],
        }),
        change('private', 'prop', 'sw-button-process', 'animationTimeout'),
    ]);
}

function names(wrapper: ReturnType<typeof mount>): string[] {
    return wrapper.findAll('[data-testid="component-row"] a').map((link) => link.text());
}

describe('api-explorer/app/views/components-view', () => {
    it('keeps a component that became private with a breaking removal visible when "Show private" is off', async () => {
        const wrapper = mount(ComponentsView, { props: { result: publicToPrivateFixture() } });

        expect(names(wrapper)).toEqual([
            'sw-product-detail',
            'sw-button-process',
        ]);

        await wrapper.find('input[type="checkbox"]').setValue(false);

        expect(names(wrapper)).toEqual(['sw-product-detail']);
        expect(wrapper.find('[data-testid="component-row"]').text()).toContain('1 Breaking');
        expect(wrapper.find('[data-testid="private-hidden"]').text()).toBe(
            '1 private component with only private changes hidden',
        );
    });

    it('hides unchanged private components on "All" and says how many', async () => {
        const wrapper = mount(ComponentsView, { props: { result: publicToPrivateFixture() } });

        await wrapper
            .findAll('.segmented__button')
            .find((button) => button.text() === 'All')!
            .trigger('click');
        await wrapper.find('input[type="checkbox"]').setValue(false);

        expect(names(wrapper)).toEqual(['sw-product-detail']);
        expect(wrapper.find('[data-testid="private-hidden"]').text()).toBe(
            '2 private components with only private changes hidden',
        );
    });
});
