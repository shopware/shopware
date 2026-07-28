/**
 * @sw-package framework
 */
import { classifyPlatform, formatShortcutKey, PLATFORM } from 'src/core/helper/shortcut-key.helper';

describe('core/helper/shortcut-key.helper', () => {
    it.each([
        [
            'MacIntel',
            PLATFORM.MAC,
        ],
        [
            'Win32',
            PLATFORM.WINDOWS,
        ],
        [
            'Linux x86_64',
            PLATFORM.LINUX,
        ],
        [
            '',
            PLATFORM.LINUX,
        ],
    ])('classifyPlatform(%s) returns %s', (rawPlatform, expected) => {
        expect(classifyPlatform(rawPlatform)).toBe(expected);
    });

    it.each([
        [
            'ALT',
            PLATFORM.MAC,
            { label: '⌥', ariaLabel: 'Option' },
        ],
        [
            'ctrl',
            PLATFORM.WINDOWS,
            { label: 'Ctrl', ariaLabel: 'Control' },
        ],
        [
            'cmd',
            PLATFORM.LINUX,
            { label: 'Super', ariaLabel: 'Super' },
        ],
        [
            'Tab',
            PLATFORM.MAC,
            { label: '⇥ Tab', ariaLabel: 'Tab' },
        ],
    ])('formatShortcutKey(%s, %s) maps to the platform symbol', (key, platform, expected) => {
        expect(formatShortcutKey(key, platform)).toEqual(expected);
    });

    it('uppercases unknown single-character keys', () => {
        expect(formatShortcutKey('s', PLATFORM.LINUX)).toEqual({ label: 'S', ariaLabel: 'S' });
    });

    it('passes through unknown multi-character keys unchanged', () => {
        expect(formatShortcutKey('Enter', PLATFORM.LINUX)).toEqual({ label: 'Enter', ariaLabel: 'Enter' });
    });
});
