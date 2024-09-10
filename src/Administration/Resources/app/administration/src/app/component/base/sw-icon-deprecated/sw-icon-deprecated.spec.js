import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-icon', { sync: true }), {
        props: {
            name: 'regular-circle-download',
        },
        global: {
            stubs: {
                'sw-icon-deprecated': await wrapTestComponent('sw-icon-deprecated', { sync: true }),
                'mt-icon': true,
                'sw-icon': true,
            },
        },
    });
}

describe('src/app/component/base/sw-icon-deprecated/index.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the correct icon (circle-download)', async () => {
        expect(wrapper.find('.sw-icon').exists()).toBeTruthy();
        expect(wrapper.find('svg#meteor-icon-kit__regular-circle-download').exists()).toBeTruthy();
    });

    it('should render the correct icon (regular-fingerprint)', async () => {
        await wrapper.setProps({
            name: 'regular-fingerprint',
        });
        await flushPromises();

        expect(wrapper.find('.sw-icon').exists()).toBeTruthy();
        expect(wrapper.find('svg#meteor-icon-kit__regular-fingerprint').exists()).toBeTruthy();
    });

    it('should render the correct color', async () => {
        await wrapper.setProps({
            color: 'rgb(123, 0, 123)',
        });

        expect(wrapper.find('.sw-icon').attributes('style')).toContain('color: rgb(123, 0, 123);');

        await wrapper.setProps({
            color: 'rgb(255, 0, 42)',
        });

        expect(wrapper.find('.sw-icon').attributes('style')).toContain('color: rgb(255, 0, 42);');
    });

    it('should render the small icon', async () => {
        expect(wrapper.find('.sw-icon--small').exists()).toBe(false);

        await wrapper.setProps({
            small: true,
        });

        expect(wrapper.find('.sw-icon--small').exists()).toBe(true);
    });

    it('should render the large icon', async () => {
        expect(wrapper.find('.sw-icon--large').exists()).toBe(false);

        await wrapper.setProps({
            large: true,
        });

        expect(wrapper.find('.sw-icon--large').exists()).toBe(true);
    });

    it('should render the icon in the correct size', async () => {
        expect(wrapper.find('.sw-icon').attributes('style')).toBeUndefined();

        await wrapper.setProps({
            size: '36px',
        });

        expect(wrapper.find('.sw-icon').attributes('style')).toContain('width: 36px; height: 36px;');
    });

    it('should have aria hidden attribute when prop is set to decorative', async () => {
        expect(wrapper.find('.sw-icon').attributes('aria-hidden')).toBeUndefined();

        await wrapper.setProps({
            decorative: true,
        });

        expect(wrapper.find('.sw-icon').attributes('aria-hidden')).toBe('true');
    });
});
