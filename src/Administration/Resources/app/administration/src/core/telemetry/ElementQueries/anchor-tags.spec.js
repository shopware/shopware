import AnchorTags from './anchor-tags';

describe('src/core/telemetry/ElementQueries/anchor-tags.js', () => {
    it('recognizes added anchor tag', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = AnchorTags(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const link = document.createElement('a');
        document.body.append(link);

        await flushPromises();

        expect(observedElements).toHaveLength(1);
        expect(observedElements[0]).toBe(link);

        mo.disconnect();
    });

    it('recognizes nested anchor tag', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = AnchorTags(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const div = document.createElement('div');
        const first = document.createElement('a');
        const second = document.createElement('a');

        div.appendChild(first);
        div.appendChild(second);

        document.body.append(div);

        await flushPromises();

        expect(observedElements).toHaveLength(2);
        expect(observedElements).toEqual([
            first,
            second,
        ]);

        mo.disconnect();
    });
});
