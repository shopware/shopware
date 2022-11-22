import HistoryUtil from 'src/utility/history/history.util';

/**
 * @package storefront
 */
describe('history.util tests', () => {
    test('returns the current location', () => {
        HistoryUtil.push('/listing', '?page=5', { some: 'state' });

        expect(HistoryUtil.getLocation()).toEqual(
            expect.objectContaining({
                hash: '',
                pathname: '/listing',
                search: '?page=5',
                state: { some: 'state' },
            })
        );
    });

    test('is able to push parameters', () => {
        HistoryUtil.push('/listing', '?page=5', { some: 'state' });
        HistoryUtil.pushParams({ 'big-image': 1, 'mode': 'detailed' }, { some: 'state' });

        expect(HistoryUtil.getSearch()).toBe('?big-image=1&mode=detailed&page=5');
    });

    test('is able to execute callback on history changes', () => {
        const callback = jest.fn();

        HistoryUtil.listen(callback);

        HistoryUtil.push('/another-route', '?page=1', { some: 'state' });
        HistoryUtil.push('/contact', '', { some: 'state' });

        expect(callback).toHaveBeenCalledTimes(2);
    });
});
