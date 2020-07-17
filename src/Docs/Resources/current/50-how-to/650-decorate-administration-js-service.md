[titleEn]: <>(Basic handling of js services in the administration)
[metaDescriptionEn]: <>(This HowTo will teach you how to register a new service and decorate an existing service via a plugin.)
[hash]: <>(article:how_to_admin_decorate_js)

## Overview

The main entry point for this purpose is the plugin's `main.js` file.
It has to be placed into the `<plugin root>/src/Resources/app/administration/src` directory in order to be automatically found by Shopware 6.

## Register a new service

For this example the following service is used to get random jokes.
It is placed in `<administration root>/services/joke.service.js`

```javascript
/**
 * @class
 * @property {AxiosInstance} httpClient
 */
export default class JokeService {
    /**
     * @constructor
     * @param {AxiosInstance} httpClient
     */
    constructor(httpClient) {
        this.httpClient = httpClient;
    }

    /**
     * @returns {Promise<{id: number, category: string, type: string, joke: ?string, setup: ?string, delivery: ?string}>}
     */
    joke() {
        return this.httpClient
            .get('https://sv443.net/jokeapi/category/Programming?blacklistFlags=nsfw,religious,political')
            .then(response => response.data)
    }
}
```

For now this service class is not available in the injection container.
To fix this, a new script is placed at `<administration root>/init/joke-service.init.js` and imported in the `main.js` file of our plugin:

```javascript
import JokeService from '../service/joke.service.js';

Shopware.Application.addServiceProvider('joker', container => {
    const initContainer = Shopware.Application.getContainer('init');
    return new JokeService(initContainer.httpClient);
});
```

## Service injection

A service is typically injected into a vue component and can simply be referenced in the `inject` property:

```javascript
Shopware.Component.register('foobar-joke', {
    inject: [
        'joker'
    ],

    created() {
        this.joker.joke().then(joke => console.log(joke))
    }
});
```

To avoid collision with other properties like computed fields or data fields there is an option to rename the service property using an object:

```javascript
Shopware.Component.register('foobar-joke', {
    inject: {
        jokeService: 'joker'
    },

    created() {
        this.jokeService.joke().then(joke => console.log(joke))
    }
});
```

## Decorating a service

Service decoration can be us in a variety of ways.
Services can be initialized right after their creation and single methods can get an altered behaviour.
Like in the service registration a script that is part of the `main.js` is needed.

If you need to alter a service method return value or add an additional parameter you can also do this using decoration.
For this example a `funny` attribute is added to the requested jokes by the previously registered `JokeService`:

```javascript
Shopware.Application.addServiceProviderDecorator('joker', joker => {
    const decoratedMethod = joker.joke;

    joker.joke = function () {
        return decoratedMethod.call(joker).then(joke => ({
            ...joke,
            funny: joke.id % 2 === 0
        }))
    };

    return joker;
});
```
