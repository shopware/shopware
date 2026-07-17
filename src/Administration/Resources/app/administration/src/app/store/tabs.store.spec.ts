describe('tabs.store', () => {
    const store = Shopware.Store.get('tabs');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.tabItems).toStrictEqual({});
    });

    it('should add a new tab item', () => {
        Shopware.Store.get('tabs').addTabItem({
            label: 'Test',
            positionId: 'examplePositionId',
            componentSectionId: 'exampleComponentSectionId',
        });

        expect(store.tabItems).toStrictEqual({
            examplePositionId: [
                {
                    label: 'Test',
                    componentSectionId: 'exampleComponentSectionId',
                    visible: undefined,
                },
            ],
        });
    });

    it('stores the visible flag when provided', () => {
        store.addTabItem({
            label: 'Hidden',
            positionId: 'examplePositionId',
            componentSectionId: 'exampleComponentSectionId',
            visible: false,
        });

        expect(store.tabItems.examplePositionId).toStrictEqual([
            {
                label: 'Hidden',
                componentSectionId: 'exampleComponentSectionId',
                visible: false,
            },
        ]);
    });

    it('upserts a tab item by componentSectionId instead of duplicating it', () => {
        store.addTabItem({
            label: 'Tab',
            positionId: 'examplePositionId',
            componentSectionId: 'exampleComponentSectionId',
            visible: false,
        });
        store.addTabItem({
            label: 'Tab',
            positionId: 'examplePositionId',
            componentSectionId: 'exampleComponentSectionId',
            visible: true,
        });

        expect(store.tabItems.examplePositionId).toStrictEqual([
            {
                label: 'Tab',
                componentSectionId: 'exampleComponentSectionId',
                visible: true,
            },
        ]);
    });

    it('keeps distinct componentSectionIds as separate items', () => {
        store.addTabItem({
            label: 'A',
            positionId: 'examplePositionId',
            componentSectionId: 'sectionA',
            visible: true,
        });
        store.addTabItem({
            label: 'B',
            positionId: 'examplePositionId',
            componentSectionId: 'sectionB',
            visible: false,
        });

        expect(store.tabItems.examplePositionId).toHaveLength(2);
    });
});
