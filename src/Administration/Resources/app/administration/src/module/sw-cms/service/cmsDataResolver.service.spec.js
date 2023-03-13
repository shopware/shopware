import './cmsDataResolver.service';

const pageMock = {
    name: 'TEST Page',
    type: 'page',
    id: '02218e0fb5e344bcbc6b89ce47bf7e7f',
    sections: [
        {
            position: 0,
            id: '60d3e4dbb4a24984989d2525476c49ab',
            blocks: [
                {
                    position: 0,
                    type: 'text',
                    id: 'a49b117588f247969f00e2585492ab0d',
                    slots: [
                        {
                            versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                            type: 'text',
                            slot: 'content',
                            translated: {
                                config: {
                                    content: {
                                        value: '<p>TEST</p>',
                                        source: 'static'
                                    },
                                    verticalAlign: {
                                        value: null,
                                        source: 'static'
                                    }
                                },
                            },
                            config: {
                                content: {
                                    value: '<p>TEST</p>',
                                    source: 'static'
                                },
                                verticalAlign: {
                                    value: null,
                                    source: 'static'
                                }
                            },
                            data: null,
                        }
                    ]
                }
            ]
        }
    ],
};


Shopware.Service().register('cmsService', () => {
    return {
        getCmsElementRegistry() {
            return {
                text: {
                    collect() {
                        return {};
                    }
                }
            };
        }
    };
});


function getService() {
    return Shopware.Service().get('cmsDataResolverService');
}

describe('module/sw-cms/service/cmsDataResolver.service.js', () => {
    it('should add visibility settings to sections', async () => {
        const service = getService();

        await service.resolve(pageMock);

        const sections = pageMock.sections;
        expect(sections).toHaveLength(1);
        expect(sections[0].visibility).toEqual({
            desktop: true,
            tablet: true,
            mobile: true,
        });
    });

    it('should add visibility settings to blocks', async () => {
        const service = getService();

        await service.resolve(pageMock);

        const sections = pageMock.sections;
        expect(sections).toHaveLength(1);

        const blocks = sections[0].blocks;
        expect(blocks).toHaveLength(1);
        expect(blocks[0].visibility).toEqual({
            desktop: true,
            tablet: true,
            mobile: true,
        });
    });
});

