import { Image } from 'image-js';

export function createRandomImage(width = 800, height = 600) {

    const buffer = Buffer.alloc(width * height * 4);

    let i = 0;
    while (i < buffer.length) {
        buffer[i++] = Math.floor(Math.random() * 256);
    }

    return new Image(width, height, buffer);
}
