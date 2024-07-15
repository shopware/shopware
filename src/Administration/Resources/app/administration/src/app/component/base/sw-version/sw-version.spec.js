/**
 * @package admin
 * group disabledCompat
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-version', () => {
    let wrapper;

    async function createWrapper(version) {
        Shopware.State.commit('context/setAppConfigVersion', version);

        wrapper = mount(await wrapTestComponent('sw-version', { sync: true }), {
            global: {
                stubs: ['sw-color-badge'],
            },
        });
    }

    it('should be a Vue.js component', async () => {
        await createWrapper('foo');

        expect(wrapper.vm).toBeTruthy();
    });

    it('should output strange version constraint', async () => {
        const version = 'strange version-dev-rc-ea';
        await createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe(version);
    });

    it('should output 3 point constraint', async () => {
        const version = '6.4.0';
        await createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe(version);
    });

    it('should output 4 point constraint', async () => {
        const version = '6.4.10.1';
        await createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe(version);
    });

    it('should convert rc modifier', async () => {
        const version = '7.0.0.0-rc';
        await createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('7.0.0.0 Release Candidate');
    });

    it('should convert dev modifier', async () => {
        const version = '7.0.0.0-dev';
        await createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('7.0.0.0 Developer Version');
    });

    it('should convert dp modifier', async () => {
        const version = '7.0.0.0-dp';
        await createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('7.0.0.0 Developer Preview');
    });

    it('should convert ea modifier', async () => {
        const version = '7.0.0.0-ea';
        await createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('7.0.0.0 Early Access');
    });

    it('should output trunk version', async () => {
        const version = '6.4.9999999.9999999-dev';
        await createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('6.4.9999999.9999999 Developer Version');
    });
});

