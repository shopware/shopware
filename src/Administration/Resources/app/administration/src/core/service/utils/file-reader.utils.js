/**
 * @module core/service/utils/file-reader
 */

function registerPromiseOnFileReader(fileReader, resolve, reject) {
    fileReader.onerror = () => {
        fileReader.abort();
        reject(new DOMException('Problem parsing file.'));
    };

    fileReader.onload = () => {
        resolve(fileReader.result);
    };
}

function splitFileNameAndExtension(completeFileName) {
    const fileParts = completeFileName.split('.');

    // no dot in filename
    if (fileParts.length === 1) {
        return {
            extension: '',
            fileName: completeFileName,
        };
    }

    // hidden file without extension
    if (fileParts.length === 2 && !fileParts[0]) {
        return {
            extension: '',
            fileName: completeFileName,
        };
    }

    return {
        extension: fileParts.pop(),
        fileName: fileParts.join('.'),
    };
}

function readFileAsArrayBuffer(inputFile) {
    const fReader = new FileReader();

    return new Promise((resolve, reject) => {
        registerPromiseOnFileReader(fReader, resolve, reject);

        fReader.readAsArrayBuffer(inputFile);
    });
}

function readFileAsDataURL(inputFile) {
    const fReader = new FileReader();

    return new Promise((resolve, reject) => {
        registerPromiseOnFileReader(fReader, resolve, reject);

        fReader.readAsDataURL(inputFile);
    });
}

function readFileAsText(inputFile) {
    const fReader = new FileReader();

    return new Promise((resolve, reject) => {
        registerPromiseOnFileReader(fReader, resolve, reject);

        fReader.readAsText(inputFile);
    });
}

/**
 * @function getNameAndExtensionFromFile
 * @param { File } fileHandle
 * @returns {*} = { extension, fileName }
 */
function getNameAndExtensionFromFile(fileHandle) {
    return splitFileNameAndExtension(fileHandle.name);
}

/**
* @function getNameAndExtensionFromUrl
* @param { URL } urlObject
* @returns {*} = { extension, fileName }
*/
function getNameAndExtensionFromUrl(urlObject) {
    let ref = urlObject.href.split('/').pop();

    const indexOfQueryIndicator = ref.indexOf('?');
    if (indexOfQueryIndicator > 0) {
        ref = ref.substring(0, indexOfQueryIndicator);
    }

    ref = decodeURI(ref);

    return splitFileNameAndExtension(ref);
}

export default {
    readFileAsArrayBuffer,
    readFileAsDataURL,
    readFileAsText,
    getNameAndExtensionFromFile,
    getNameAndExtensionFromUrl,
};
