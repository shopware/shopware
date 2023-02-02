import Iterator from 'src/helper/iterator.helper.js';

/**
 * @package storefront
 */
describe('iterator.helper.js', () => {
    test('it iterates over maps', () => {
        const testMap = new Map();
        testMap.set('first', 1);
        testMap.set('second', 1);
        testMap.set('third', 1);

        const callback = jest.fn();
        Iterator.iterate(testMap, callback);

        expect(callback).toBeCalledTimes(3);
        expect(callback).nthCalledWith(1, 1, 'first', testMap);
        expect(callback).nthCalledWith(2, 1, 'second', testMap);
        expect(callback).nthCalledWith(3, 1, 'third', testMap);
    });

    test('it iterates over arrays', () => {
        const arr = [1,2,3];

        const callback = jest.fn();
        Iterator.iterate(arr, callback);

        expect(callback).toBeCalledTimes(3);
        expect(callback).nthCalledWith(1, 1, 0, arr);
        expect(callback).nthCalledWith(2, 2, 1, arr);
        expect(callback).nthCalledWith(3, 3, 2, arr);
    });

    test('it iterates over formData', () => {
        const formData = new FormData();
        formData.append('first', 1);
        formData.append('second', 2);
        formData.append('third', 3);

        const callback = jest.fn();
        Iterator.iterate(formData, callback);

        expect(callback).toBeCalledTimes(3);
        expect(callback).nthCalledWith(1, '1', 'first');
        expect(callback).nthCalledWith(2, '2', 'second');
        expect(callback).nthCalledWith(3, '3', 'third');
    });

    test('it iterates over objects', () => {
        const objectToIterate = {
            a: 1,
            b: 2,
            c: 3,
        };

        const callback = jest.fn();
        Iterator.iterate(objectToIterate, callback);

        expect(callback).toBeCalledTimes(3);
        expect(callback).nthCalledWith(1, 1, 'a');
        expect(callback).nthCalledWith(2, 2, 'b');
        expect(callback).nthCalledWith(3, 3, 'c');
    });

    test('it iterates node lists', () => {
        document.body.innerHTML = '<ul><li>first</li><li>second</li><li>third</li></ul>';
        const nodeList = document.querySelectorAll('li');

        const callback = jest.fn();
        Iterator.iterate(nodeList, callback);

        expect(callback).toBeCalledTimes(3);
        expect(callback).nthCalledWith(1, nodeList[0], 0, nodeList);
        expect(callback).nthCalledWith(2, nodeList[1], 1, nodeList);
        expect(callback).nthCalledWith(3, nodeList[2], 2, nodeList);

    });

    test('it iterates HTML collections', () => {
        document.body.innerHTML = '<ul><li>first</li><li>second</li><li>third</li></ul>';
        const nodeList = document.getElementsByTagName('li');
        expect(nodeList.length).toBe(3);

        const callback = jest.fn();
        Iterator.iterate(nodeList, callback);

        expect(callback).toBeCalledTimes(3);

        const arrayNodeList = Array.from(nodeList);
        expect(nodeList.length).toBe(arrayNodeList.length);

        expect(callback).nthCalledWith(1, arrayNodeList[0], 0, arrayNodeList);
        expect(callback).nthCalledWith(2, arrayNodeList[1], 1, arrayNodeList);
        expect(callback).nthCalledWith(3, arrayNodeList[2], 2, arrayNodeList);

    });

    test('it throws for primitives', () => {
        expect(() => { return Iterator.iterate('iterate over string') }).toThrowError();
        expect(() => { return Iterator.iterate(new String('iterate over string')) }).toThrowError();
        expect(() => { return Iterator.iterate(42) }).toThrowError();
    });

    test('it throws for null and undefined', () => {
        expect(() => { return Iterator.iterate(null) }).toThrowError();
        expect(() => { return Iterator.iterate(undefined) }).toThrowError();
    });
});
