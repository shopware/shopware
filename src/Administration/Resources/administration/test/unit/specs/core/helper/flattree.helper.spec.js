import FlatTree from 'src/core/helper/flattree.helper';

describe('core/helper/flattree.helper.js', () => {
    let flatTree;

    beforeEach(() => {
        flatTree = new FlatTree();
    });

    it('should register new nodes', () => {
        flatTree.add({
            label: 'Foobar',
            path: 'sw.foo.bar'
        }).add({
            label: 'BarBatz',
            path: 'sw.bar.batz'
        });

        expect(flatTree.getRegisteredNodes().size).to.equal(2);
    });

    it('should not be possible to register different nodes with the same path', () => {
        flatTree.add({
            label: 'Foobar',
            path: 'sw.foo.bar'
        }).add({
            label: 'BarBatz',
            path: 'sw.foo.bar'
        });

        expect(flatTree.getRegisteredNodes().size).to.equal(1);
    });

    it('should be possible to remove nodes from the tree', () => {
        flatTree.add({
            label: 'Foobar',
            path: 'sw.foo.bar'
        }).remove('sw.foo.bar');

        expect(flatTree.getRegisteredNodes().size).to.equal(0);
    });

    it('should not remove a node when the node identifier does not match', () => {
        flatTree.add({
            label: 'Foobar',
            path: 'sw.foo.bar'
        }).remove('sw.foo.batz');

        expect(flatTree.getRegisteredNodes().size).to.equal(1);
    });

    it('should be possible to get all registered nodes', () => {
        expect(flatTree.getRegisteredNodes()).to.be.a('Map');
    });

    it('should be possible to nest child nodes infinitely (10 levels here)', () => {
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

        expect(flatTree.getRegisteredNodes().size).to.equal(10);
    });

    it('should create a tree hierarchy', () => {
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

        expect(flatTree.convertToTree()).to.deep.equal([{
            path: 'sw.a',
            children: [{
                path: 'sw.b',
                parent: 'sw.a',
                children: []
            }]
        }, {
            path: 'sw.c',
            children: []
        }]);
    });
});
