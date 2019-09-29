[titleEn]: <>(Basic handling of js services in the administration)
[metaDescriptionEn]: <>(This HowTo will teach how to register a new service and decorate an existing service via a plugin.)

## Overview

The main entry point for this purpose is the plugin's `main.js` file.
It has to be placed into the `<plugin root>/src/Resources/administration` directory in order to be automatically found by Shopware 6.
*Note: This path can be changed by overriding the [getAdministrationEntryPath()](../2-internals/4-plugins/020-plugin-base-class.md#getAdministrationEntryPath()) method of the plugin's base class.*

## Register a new service

Service classes itself are often found in a `service` directory.
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

For now this service class just exists but is not available by the injection container.
The next step is a script that registers a new instance in the injection container.
Therefore a new file is placed at `<administration root>/init/joke-service.init.js` and imported from the `main.js`:

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

For an extended initialization example the locale service gets new snippets assigned like this:
 
```javascript
import snippets from '../snippet/en-GB.json';

Shopware.Application.addInitializerDecorator('locale', locale => {
    locale.extend('en-GB', snippets);
    return locale;
});
```

This function is called right after the initialization of the service registration and adds snippets for the code `en-GB`.

In case of altering a service method result this is a way to alter the output.
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
