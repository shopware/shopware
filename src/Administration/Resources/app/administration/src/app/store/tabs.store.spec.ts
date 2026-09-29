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

    it('toggles the visibility of an existing tab item', () => {
        store.addTabItem({
            label: 'Tab',
            positionId: 'examplePositionId',
            componentSectionId: 'exampleComponentSectionId',
            visible: true,
        });

        store.setVisibility({
            positionId: 'examplePositionId',
            componentSectionId: 'exampleComponentSectionId',
            visible: false,
        });

        expect(store.tabItems.examplePositionId).toStrictEqual([
            {
                label: 'Tab',
                componentSectionId: 'exampleComponentSectionId',
                visible: false,
            },
        ]);
    });

    it('warns and does nothing when setting visibility for an unknown tab item', () => {
        const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();

        store.addTabItem({
            label: 'Tab',
            positionId: 'examplePositionId',
            componentSectionId: 'exampleComponentSectionId',
            visible: true,
        });

        store.setVisibility({
            positionId: 'examplePositionId',
            componentSectionId: 'unknownComponentSectionId',
            visible: false,
        });

        store.setVisibility({
            positionId: 'unknownPositionId',
            componentSectionId: 'exampleComponentSectionId',
            visible: false,
        });

        expect(store.tabItems.examplePositionId).toStrictEqual([
            {
                label: 'Tab',
                componentSectionId: 'exampleComponentSectionId',
                visible: true,
            },
        ]);

        expect(warnSpy).toHaveBeenCalledWith(
            'TabsStore',
            'Cannot set visibility for unknown tab item "unknownComponentSectionId" at position "examplePositionId"',
        );
        expect(warnSpy).toHaveBeenCalledWith(
            'TabsStore',
            'Cannot set visibility for unknown tab item "exampleComponentSectionId" at position "unknownPositionId"',
        );
    });
});
