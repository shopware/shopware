import FlatTree from 'src/core/helper/flattree.helper';

// Disable developer hints in jest output
const warnSpy = jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('core/helper/flattree.helper.js', () => {
    let flatTree;

    beforeEach(() => {
        flatTree = new FlatTree();
    });

    it('should register new nodes', () => {
        flatTree.add({
            label: 'Foobar',
            id: 'sw.foo.bar'
        }).add({
            label: 'BarBatz',
            id: 'sw.bar.batz'
        });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'sw.foo.bar'
            }), expect.objectContaining({
                id: 'sw.bar.batz'
            })
        ]);
    });

    it('expects a node to have a path or id property', () => {
        flatTree.add({
            label: 'with path',
            path: '/foo/bar'
        }).add({
            label: 'with id',
            id: 'foo.bar'
        }).add({
            label: 'without id and path'
        });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                label: 'with path'
            }), expect.objectContaining({
                label: 'with id'
            })
        ]);

        expect(warnSpy).toBeCalledWith(
            '[FlatTree]',
            'The node needs an "id" or "path" property. Abort registration.',
            expect.objectContaining({
                label: 'without id and path'
            })
        );
    });

    it('should not be possible to register different nodes with the same id', () => {
        flatTree.add({
            label: 'Foobar',
            id: 'sw.foo.bar'
        }).add({
            label: 'BarBatz',
            id: 'sw.foo.bar'
        });

        const tree = flatTree.convertToTree();

        expect(tree).toHaveLength(1);
        expect(tree[0]).toEqual({
            position: 1,
            level: 1,
            label: 'Foobar',
            id: 'sw.foo.bar',
            children: []
        });
    });

    it('automatically sets target to _self if a link is specified', () => {
        flatTree.add({
            id: 'foo.bar',
            link: 'https://shopware.com'
        });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'foo.bar',
                link: 'https://shopware.com',
                target: '_self'
            })
        ]);
    });

    it('should be possible to remove nodes from the tree', () => {
        flatTree.add({
            label: 'Foobar',
            id: 'sw.foo.bar'
        });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'sw.foo.bar'
            })
        ]);

        flatTree.remove('sw.foo.bar');

        expect(flatTree.convertToTree()).toHaveLength(0);
    });

    it('should not remove a node when the node identifier does not match', () => {
        flatTree.add({
            label: 'Foobar',
            id: 'sw.foo.bar'
        }).remove('sw.foo.batz');

        expect(flatTree.convertToTree()).not.toEqual(expect.arrayContaining[
            expect.objectContaining({
                id: 'sw.foo.bar'
            })
        ]);
    });

    it('should be possible to nest child nodes infinitely (4 levels here)', () => {
        flatTree.add({
            id: 'sw.a'
        }).add({
            id: 'sw.b',
            parent: 'sw.a'
        }).add({
            id: 'sw.c',
            parent: 'sw.b'
        }).add({
            id: 'sw.d',
            parent: 'sw.c'
        });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'sw.a',
                level: 1,
                children: [expect.objectContaining({
                    id: 'sw.b',
                    level: 2,
                    children: [expect.objectContaining({
                        id: 'sw.c',
                        level: 3,
                        children: [expect.objectContaining({
                            id: 'sw.d',
                            level: 4,
                            children: []
                        })]
                    })]
                })]
            })
        ]);
    });

    it('should create a tree hierarchy', () => {
        flatTree.add({
            id: 'sw.a'
        }).add({
            id: 'sw.a_child_1',
            parent: 'sw.a'
        }).add({
            id: 'sw.b'
        }).add({
            id: 'sw.a_child_2',
            parent: 'sw.a'
        })
            .add({
                id: 'sw.b_child_1',
                parent: 'sw.b'
            });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'sw.a',
                level: 1,
                children: [
                    expect.objectContaining({
                        id: 'sw.a_child_1',
                        parent: 'sw.a',
                        level: 2
                    }), expect.objectContaining({
                        id: 'sw.a_child_2',
                        parent: 'sw.a',
                        level: 2
                    })
                ]
            }),
            expect.objectContaining({
                id: 'sw.b',
                level: 1,
                children: [
                    expect.objectContaining({
                        id: 'sw.b_child_1',
                        parent: 'sw.b',
                        level: 2
                    })
                ]
            })
        ]);
    });

    it('respects sorting function for children if given', () => {
        flatTree = new FlatTree((first, second) => {
            return first.position - second.position;
        });

        flatTree.add({
            id: 'sw.30',
            position: 30
        }).add({
            id: 'sw.10',
            position: 10
        }).add({
            id: 'sw.20',
            position: 20
        }).add({
            id: 'sw.40',
            position: 40
        });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                position: 10
            }), expect.objectContaining({
                position: 20
            }), expect.objectContaining({
                position: 30
            }), expect.objectContaining({
                position: 40
            })
        ]);
    });

    /** @deprecated tag:v6.5.0 can be removed when defaultPosition is removed */
    describe('deprecated functions ', () => {
        it('should be possible to get all registered nodes', () => {
            expect(flatTree.getRegisteredNodes()).toBeInstanceOf(Map);
        });

        it('can set and get the default position', () => {
            expect(flatTree.defaultPosition).toBe(1);

            flatTree.defaultPosition = -100;

            expect(flatTree.defaultPosition).toBe(-100);
        });
    });
});
