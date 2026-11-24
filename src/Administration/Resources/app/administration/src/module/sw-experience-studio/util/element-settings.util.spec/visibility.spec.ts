import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import { isPropertyVisible } from '../element-settings.util';
import { stringProperty } from './fixtures';

describe('element settings field visibility', () => {
    it('supports visibleWhen equals and notEquals operators', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            equals: 'explicit',
                        },
                    },
                },
                { mode: 'explicit' },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            notEquals: 'explicit',
                        },
                    },
                },
                { mode: 'auto-fit' },
            ),
        ).toBe(true);
    });

    it('supports visibleWhen in and notIn operators', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            in: [
                                'auto-fit',
                                'auto-fill',
                            ],
                        },
                    },
                },
                { mode: 'auto-fill' },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            notIn: [
                                'explicit',
                                'max-content',
                            ],
                        },
                    },
                },
                { mode: 'auto-fit' },
            ),
        ).toBe(true);
    });

    it('supports visibleWhen isEmpty and isNotEmpty operators', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'headline',
                            isEmpty: true,
                        },
                    },
                },
                { headline: '' },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'headline',
                            isNotEmpty: true,
                        },
                    },
                },
                { headline: 'Shopware' },
            ),
        ).toBe(true);
    });

    it('applies AND semantics when visibleWhen is an array', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: [
                            {
                                field: 'mode',
                                equals: 'explicit',
                            },
                            {
                                field: 'headline',
                                isNotEmpty: true,
                            },
                        ],
                    },
                },
                {
                    mode: 'explicit',
                    headline: 'Visible',
                },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: [
                            {
                                field: 'mode',
                                equals: 'explicit',
                            },
                            {
                                field: 'headline',
                                isNotEmpty: true,
                            },
                        ],
                    },
                },
                {
                    mode: 'explicit',
                    headline: '',
                },
            ),
        ).toBe(false);
    });

    it('is fail-safe for malformed visibleWhen conditions', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            unknownOperator: 'explicit',
                        },
                    },
                } as ContentSystemElementTypeProperty,
                { mode: 'explicit' },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            equals: 'explicit',
                            in: ['explicit'],
                        },
                    },
                },
                { mode: 'explicit' },
            ),
        ).toBe(true);
    });

    it('handles missing fields without crashing', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'missingField',
                            equals: 'explicit',
                        },
                    },
                },
                { mode: 'explicit' },
            ),
        ).toBe(false);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'missingField',
                            isEmpty: true,
                        },
                    },
                },
                { mode: 'explicit' },
            ),
        ).toBe(true);
    });
});
