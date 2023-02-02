import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-version';

describe('components/base/sw-version', () => {
    let wrapper;

    function createWrapper(version) {
        Shopware.State.commit('context/setAppConfigVersion', version);

        wrapper = shallowMount(Shopware.Component.build('sw-version'), { stubs: ['sw-color-badge'] });
    }

    afterEach(() => { if (wrapper) wrapper.destroy(); });

    it('should be a Vue.js component', async () => {
        createWrapper('foo');

        expect(wrapper.vm).toBeTruthy();
    });

    it('should output strange version constraint', async () => {
        const version = 'strange version-dev-rc-ea';
        createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe(version);
    });

    it('should output 3 point constraint', async () => {
        const version = '6.4.0';
        createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe(version);
    });

    it('should output 4 point constraint', async () => {
        const version = '6.4.10.1';
        createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe(version);
    });

    it('should convert rc modifier', async () => {
        const version = '7.0.0.0-rc';
        createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('7.0.0.0 Release Candidate');
    });

    it('should convert dev modifier', async () => {
        const version = '7.0.0.0-dev';
        createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('7.0.0.0 Developer Version');
    });

    it('should convert dp modifier', async () => {
        const version = '7.0.0.0-dp';
        createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('7.0.0.0 Developer Preview');
    });

    it('should convert dp modifier', async () => {
        const version = '7.0.0.0-ea';
        createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('7.0.0.0 Early Access');
    });

    it('should output trunk version', async () => {
        const version = '6.4.9999999.9999999-dev';
        createWrapper(version);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.version).toBe('6.4.9999999.9999999 Developer Version');
    });
});

