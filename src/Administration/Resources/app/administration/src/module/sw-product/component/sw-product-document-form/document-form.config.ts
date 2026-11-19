/**
 * @sw-package inventory
 */

/** @private */
export default [
    {
        property: 'number',
        label: '#',
        width: '56px',
        align: 'center',
    },
    {
        property: 'position',
        label: 'sw-product.documentForm.columnPosition',
        width: '110px',
        align: 'center',
    },
    {
        property: 'title',
        label: 'sw-product.documentForm.columnTitle',
        primary: true,
        width: '300px',
    },
    {
        property: 'fileName',
        label: 'sw-product.documentForm.columnFileName',
        primary: true,
        width: '300px',
    },
    {
        property: 'fileType',
        label: 'sw-product.documentForm.columnFileType',
        visible: false,
        width: '104px',
    },
    {
        property: 'fileSize',
        label: 'sw-product.documentForm.columnFileSize',
        width: '100px',
        visible: false,
        align: 'right',
    },
].map((column) => ({
    ...column,
    sortable: false,
    allowResize: !['number', 'position'].includes(column.property),
}));

/** @private */
export const allowedExtensions = [
    'pdf',
    'docx',
    'jpg',
    'jpeg',
    'png',
];
/** @private */
export const maxFileSize = 20 * 1024 * 1024;
