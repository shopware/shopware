import { createCanvas } from 'canvas';

export function createPNGImage(id: string, imageName: string) : Uint8Array {

    const image = {
        id: id,
        name: imageName,
        width: 1200,
        height: 627,
        color: '#189eff',
        mimeType: 'image/png',
    }

    // Instantiate the canvas object
    const canvas = createCanvas(image.width, image.height);
    const context = canvas.getContext('2d');

    // Fill the rectangle with a color
    context.fillStyle = image.color;
    context.fillRect(0, 0, image.width, image.height);

    // Write the image file to a buffer
    return canvas.toBuffer(image.mimeType);
}
