/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { productDetailFixture } from '../__fixtures__/compare-fixture';
import { ShopwareObjectView } from './shopware-object-view';

function mountObjectView(path: string | null = null) {
    return mount(ShopwareObjectView, { props: { result: productDetailFixture(), path } });
}

function paths(wrapper: ReturnType<typeof mountObjectView>): string[] {
    return wrapper.findAll('[data-testid="object-row"]').map((row) => row.attributes('data-path')!);
}

describe('api-explorer/app/views/shopware-object-view', () => {
    it('hides private nodes and their children when "Show private" is off', async () => {
        const wrapper = mountObjectView();
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Expand all')!
            .trigger('click');

        expect(paths(wrapper)).toEqual(
            expect.arrayContaining([
                'Shopware.Internal',
                'Shopware.Internal.secret',
            ]),
        );

        await wrapper.find('input[type="checkbox"]').setValue(false);

        expect(paths(wrapper)).toEqual([
            'Shopware',
            'Shopware.Component',
            'Shopware.Component.register',
            'Shopware.Component.build',
        ]);
    });

    it('keeps a private container visible while it holds a public change', async () => {
        const result = productDetailFixture();
        result.head.shopwareObject.children[0].visibility = 'private';
        const wrapper = mount(ShopwareObjectView, { props: { result, path: null } });

        await wrapper.find('input[type="checkbox"]').setValue(false);

        expect(paths(wrapper)).toEqual([
            'Shopware',
            'Shopware.Component',
            'Shopware.Component.register',
            'Shopware.Component.build',
        ]);
        expect(wrapper.find('[data-testid="private-hidden"]').text()).toBe('1 private entry without public changes hidden');
    });

    it('expands the way to changed nodes and keeps only changes on "Changes only"', async () => {
        const wrapper = mountObjectView();

        expect(paths(wrapper)).toEqual([
            'Shopware',
            'Shopware.Component',
            'Shopware.Component.register',
            'Shopware.Component.build',
            'Shopware.Internal',
            'Shopware.Internal.secret',
        ]);

        await wrapper.find('input[type="checkbox"]').setValue(false);
        await wrapper
            .findAll('.segmented__button')
            .find((button) => button.text() === 'Changes only')!
            .trigger('click');

        expect(paths(wrapper)).toEqual([
            'Shopware',
            'Shopware.Component',
            'Shopware.Component.register',
            'Shopware.Component.build',
        ]);
    });

    it('collapses and expands a node on repeated clicks', async () => {
        const wrapper = mountObjectView();
        const component = () => wrapper.find('[data-path="Shopware.Component"]');

        await component().trigger('click');
        expect(component().attributes('aria-expanded')).toBe('true');

        await component().trigger('click');
        expect(component().attributes('aria-expanded')).toBe('false');
        expect(paths(wrapper)).not.toContain('Shopware.Component.register');
    });

    it('shows the signature before and after for a changed node', () => {
        const wrapper = mountObjectView('Shopware.Component.register');
        const detail = wrapper.find('[data-testid="object-detail"]');

        expect(detail.find('h2').text()).toBe('Shopware.Component.register');
        expect(detail.find('.signature--before').text()).toBe('() => void');
        expect(detail.find('.signature--after').text()).toBe('(name: string) => void');
    });
});
