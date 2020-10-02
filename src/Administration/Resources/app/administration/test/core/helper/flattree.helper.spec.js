import FlatTree from 'src/core/helper/flattree.helper';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('core/helper/flattree.helper.js', () => {
    let flatTree;

    beforeEach(() => {
        flatTree = new FlatTree();
    });

    it('should register new nodes', async () => {
        flatTree.add({
            label: 'Foobar',
            path: 'sw.foo.bar'
        }).add({
            label: 'BarBatz',
            path: 'sw.bar.batz'
        });

        expect(flatTree.getRegisteredNodes().size).toBe(2);
    });

    it(
        'should not be possible to register different nodes with the same path',
        async () => {
            flatTree.add({
                label: 'Foobar',
                path: 'sw.foo.bar'
            }).add({
                label: 'BarBatz',
                path: 'sw.foo.bar'
            });

            expect(flatTree.getRegisteredNodes().size).toBe(1);
        }
    );

    it('should be possible to remove nodes from the tree', async () => {
        flatTree.add({
            label: 'Foobar',
            path: 'sw.foo.bar'
        }).remove('sw.foo.bar');

        expect(flatTree.getRegisteredNodes().size).toBe(0);
    });

    it(
        'should not remove a node when the node identifier does not match',
        async () => {
            flatTree.add({
                label: 'Foobar',
                path: 'sw.foo.bar'
            }).remove('sw.foo.batz');

            expect(flatTree.getRegisteredNodes().size).toBe(1);
        }
    );

    it('should be possible to get all registered nodes', async () => {
        expect(flatTree.getRegisteredNodes()).toBeInstanceOf(Map);
    });

    it(
        'should be possible to nest child nodes infinitely (10 levels here)',
        async () => {
            flatTree
                .add({
                    path: 'sw.a'
                })
                .add({
                    path: 'sw.b',
                    parent: 'sw.a'
                })
                .add({
                    path: 'sw.c',
                    parent: 'sw.b'
                })
                .add({
                    path: 'sw.d',
                    parent: 'sw.c'
                })
                .add({
                    path: 'sw.e',
                    parent: 'sw.d'
                })
                .add({
                    path: 'sw.f',
                    parent: 'sw.e'
                })
                .add({
                    path: 'sw.g',
                    parent: 'sw.f'
                })
                .add({
                    path: 'sw.h',
                    parent: 'sw.g'
                })
                .add({
                    path: 'sw.i',
                    parent: 'sw.h'
                })
                .add({
                    path: 'sw.j',
                    parent: 'sw.i'
                });

            expect(flatTree.getRegisteredNodes().size).toBe(10);
        }
    );

    it('should create a tree hierarchy', async () => {
        flatTree
            .add({
                path: 'sw.a'
            })
            .add({
                path: 'sw.b',
                parent: 'sw.a'
            })
            .add({
                path: 'sw.c'
            });

        expect(flatTree.convertToTree()).toEqual([{
            path: 'sw.a',
            position: flatTree.defaultPosition,
            children: [{
                path: 'sw.b',
                parent: 'sw.a',
                position: flatTree.defaultPosition,
                children: []
            }]
        }, {
            path: 'sw.c',
            position: flatTree.defaultPosition,
            children: []
        }]);
    });
});
