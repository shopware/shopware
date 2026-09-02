/**
 * @sw-package framework
 */

import { registerNativeExtensionTargets, getNativeBlockExtensionTargets } from 'src/core/factory/native-extension-targets';

describe('core/factory/native-extension-targets', () => {
    it('collects the block names of every registered override', () => {
        registerNativeExtensionTargets({ component: 'net-a', blocks: ['net_first', 'net_second'] });
        registerNativeExtensionTargets({ component: 'net-b', blocks: ['net_third'] });

        const targets = getNativeBlockExtensionTargets();

        expect(targets.has('net_first')).toBe(true);
        expect(targets.has('net_second')).toBe(true);
        expect(targets.has('net_third')).toBe(true);
        expect(targets.has('net_unregistered')).toBe(false);
    });

    it('accepts an override without blocks', () => {
        // A template-less override registers its component but extends no block.
        expect(() => registerNativeExtensionTargets({ component: 'net-c' })).not.toThrow();
    });
});
