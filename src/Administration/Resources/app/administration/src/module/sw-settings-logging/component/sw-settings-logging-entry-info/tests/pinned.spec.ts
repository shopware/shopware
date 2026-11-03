import {
    MT_TABS,
    SW_TABS,
    chooseTab,
    getMeteorTabsProps,
    getShownRawContent,
    mountEntryInfo,
    unmountEntryInfos,
} from './entry-info.harness';

/**
 * @sw-package framework
 */

describe('sw-settings-logging-entry-info', () => {
    afterEach(unmountEntryInfos);

    it('shows an empty raw tab when the log entry has no context', async () => {
        // ACT
        const wrapper = await mountEntryInfo({ context: null });

        // ASSERT
        expect(getShownRawContent(wrapper)).toBe('');
    });

    it('shows the older tabs with the raw tab active while the 6.8 feature is off', async () => {
        // ACT
        const wrapper = await mountEntryInfo({ context: { foo: 'bar' } });

        // ASSERT
        expect(wrapper.find(SW_TABS).exists()).toBe(true);
        expect(wrapper.find('.sw-tabs-item').attributes('data-active')).toBe('true');
        expect(wrapper.find('.sw-tabs-item').text()).toBe('sw-settings-logging.entryInfo.tabRaw');
        expect(wrapper.findComponent({ name: MT_TABS }).exists()).toBe(false);
    });

    it('gives the meteor tabs only the raw tab, active, while the 6.8 feature is on', async () => {
        // ACT
        const wrapper = await mountEntryInfo({ context: { foo: 'bar' } }, { majorFeature: true });

        // ASSERT
        expect(wrapper.find(SW_TABS).exists()).toBe(false);
        expect(getMeteorTabsProps(wrapper)).toEqual({
            positionIdentifier: 'sw-settings-logging-entry-info',
            defaultItem: 'raw',
            items: [{ label: 'sw-settings-logging.entryInfo.tabRaw', name: 'raw' }],
            useRoutesForExtensions: false,
            small: true,
        });
    });

    it('hides the raw content when the meteor tabs choose another tab', async () => {
        // ARRANGE
        const wrapper = await mountEntryInfo({ context: { foo: 'bar' } }, { majorFeature: true });

        // ACT
        await chooseTab(wrapper, 'other');

        // ASSERT
        expect(getShownRawContent(wrapper)).toBeNull();
    });
});
