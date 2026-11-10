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
        width: '190px',
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
].map((column) => ({ ...column, sortable: false, resizable: false }));

// Epic defaults until validation configuration is exposed by the backend.
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
/** @private */
export const maxDocuments = 10;
