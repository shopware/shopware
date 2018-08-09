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

export default {
    readFileAsArrayBuffer,
    readFileAsDataURL,
    readFileAsText
};
