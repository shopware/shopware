[titleEn]: <>(Deprecation handling)
[hash]: <>(article:administration_deprecation_handling)

This short guide will introduce you to the best practices when you need to deprecate something in the administration.

For better understanding we are using an example. Lets imagine you want to add tabs to the theme manager. To do this it could be possible that you need to change the data structure of the `themeFields`. This structure change would break existing plugins. Therefore we need to support the old and the new structure.

### Response Structure:

#### Old data structure:
```json
{
	"themeColors": {
		"label": "Theme colours",
		"sections": {
			"color": {
				"label": "Color",
				"sw-color-brand-primary": {
					"label": "Primary colour",
					"helpText": null,
					"type": "color",
					"custom": null
				}
			}
		}
	},
	"media": {
		"label": "Media",
		"sections": {
			"logos": {
				"label": "Logos",
				"sw-logo-desktop": {
					"label": "Desktop",
					"helpText": "Displayed for viewports of above 991px",
					"type": "media",
					"custom": null
				}
			}
		}
	},
	"unordered": {
		"label": "Misc",
		"sections": []
	}
}
```

#### New data structure:
```json
{
	"tabs": {
		"default": {
			"label": "",
			"blocks": {
				"themeColors": {
					"label": "Theme colours",
					"sections": {
						"color": {
							"label": "Color",
							"fields": {
								"sw-color-brand-primary": {
									"label": "Primary colour",
									"helpText": null,
									"type": "color",
									"custom": null
								}
							}
						}
					}
				}
			}
		},
		"media": {
			"label": "Media",
			"blocks": {
				"media": {
					"label": "Media",
					"sections": {
						"logos": {
							"label": "Logos",
							"fields": {
								"sw-logo-desktop": {
									"label": "Desktop",
									"helpText": "Displayed for viewports of above 991px",
									"type": "media",
									"custom": null
								}
							}
						}
					}
				}
			}
		}
	}
}
```

To do this we need to duplicate all places where the data can be fetched and where it is used. Some examples are services, component data or state.

In our example we use the `themeFields` in three files:

### File Structure:
- sw-theme-manager-detail/**index.js**
- sw-theme-manager-detail/**sw-theme-manager-detail.html.twig**
- **theme.api.service.js**

### Services:
We want to get a new data structure from the api. Therefore you should use a new route which replaces the current route. This could be done with creating a new route under a new name or you are using an newer api version. 

You should create the new service method under a new name. This allows you to deprecate the old method. Another way to solve this problem is to use a new parameter which let you switch between two logics.

#### Before (theme.api.service.js):
```js
getFields(themeId) {
    const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}/fields`;

    const additionalHeaders = {};

    return this.httpClient.get(
        apiRoute,
        {
            headers: this.getBasicHeaders(additionalHeaders)
        }
    ).then((response) => {
        return ApiService.handleResponse(response);
    });
}
```

#### After with two different route names (theme.api.service.js):
```js
/**
 * @deprecated tag:v6.4.0 - use getStructuredFields instead
 */
getFields(themeId) {
    const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}/fields`;

    return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
          return ApiService.handleResponse(response);
      });
}

getStructuredFields(themeId) {
    const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}/structured-fields`;

    return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
          return ApiService.handleResponse(response);
      });
}
```

#### After with the same route name and different version (theme.api.service.js):
If you want use the same route but with a different version id you can add a new parameter for the version. If you call the method without a version it will request the route with the latest supported version. Therefore the response is the same and will not break existing plugins.

If you want to get the new data structure you can set the version in the parameter: `getFields(themeId, 2)`. Now you are requesting the version 2 of this route. Then you get the new data structure from the api.

```js
/**
 * @deprecated tag:v6.4.0 - use param version with value 2 instead
 * @param themeId
 * @param version
 * @returns {Promise<AxiosResponse<T>>}
 */
getFields(themeId, version = undefined) {
    const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}/fields`;
    const config = {
        headers: this.getBasicHeaders()
    }

    if (version !== undefined) {
        config.version = version;
    }

    return this.httpClient.get(apiRoute, config).then((response) => {
        return ApiService.handleResponse(response);
    });
}
```

### Component:
Now you can use the new method in the component.

It is important that all relevant data is duplicated to prevent plugins to break. `themeFields` is replaced by `structuredThemeFields`. The old route fetches the old data structure and the new route the new one. Therefore no plugin will break if the plugin developer does not update his plugin.

#### Before (sw-theme-manager-detail/**index.js**):
```js
import template from './sw-theme-manager-detail.html.twig';

Shopware.Component.register('sw-theme-manager-detail', {
    template,

    data() {
        return {
            themeFields: {}
        };
    },

    methods: {
        createdComponent() {
            this.getThemeConfig();
        },

        getThemeConfig() {
            this.themeService.getFields(this.themeId).then((fields) => {
                this.themeFields = fields;
            });
        }
    }
});
```
#### After with two different route names (sw-theme-manager-detail/**index.js**):
```js
import template from './sw-theme-manager-detail.html.twig';

Shopware.Component.register('sw-theme-manager-detail', {
    template,

    data() {
        return {
            /** @deprecated tag:v6.3.0 - use structuredThemeFields instead */
            themeFields: {},
            structuredThemeFields: {}
        };
    },

    methods: {
        createdComponent() {
            this.getThemeConfig();
        },

        getThemeConfig() {
            this.themeService.getStructuredFields(this.themeId).then((fields) => {
                this.structuredThemeFields = fields;
            });

            /** @deprecated tag:v6.4.0 */
            this.themeService.getFields(this.themeId).then((fields) => {
                this.themeFields = fields;
            });
        }
    }
});
```

#### After with the same route name and different version (sw-theme-manager-detail/**index.js**):
```js
...

getThemeConfig() {
    this.themeService.getFields(this.themeId, 2).then((fields) => {
        this.structuredThemeFields = fields;
    });

    /** @deprecated tag:v6.4.0 */
    this.themeService.getFields(this.themeId).then((fields) => {
        this.themeFields = fields;
    });
}

...
```

How you implement this is open to you. You can fetch data twice, convert data structure or do what is the best in your case. There is no general solution for every problem. You only need to be aware of that nothing should break with your changes.

This approach has the big benefit that you do not need additional requests only for deprecations. The biggest downside is that it could be depending on your situation a very complicated data transformation. Also if you have a bug in your transformation it could be happen that some plugins will break.

Another thing which makes this method more complicated is that you can also write the variable. This have to be done in two ways. Then you need a getter and setter for the value. The setter needs to update the new structure when changes to the old structure are made. One way to do this to create a computed value for the old value.

#### Example with transforming data structure and save it to data value:
```js
getThemeConfig() {
    this.themeService.getStructuredFields(this.themeId).then((fields) => {
        this.structuredThemeFields = fields;

		// basic deprecated structure
		const themeFields = {
			unordered: {
			label: "Misc",
			sections: []
			}
		};
		
		// transform new structure to deprecated structure
		Object.entries(fields.tabs).forEach(([tabName, tabValue]) => {
			Object.entries(tabValue.blocks).forEach(([blockName, blockValue]) => {
				Object.entries(blockValue.sections).forEach(([sectionName, sectionValue]) => {
					Object.entries(sectionValue.fields).forEach(([fieldName, fieldValue]) => {
						if (!themeFields[blockName]) {
							themeFields[blockName] = { label: sectionName, sections: {} }
						}

						if (!themeFields[blockName].sections[sectionName]) {
							themeFields[blockName].sections[sectionName] = { label: blockName }
						}

						themeFields[blockName].sections[sectionName][fieldName] = fieldValue;
					})
				})
			})
		});

		// assign deprecated structure to deprecated data variable
		this.themeFields = themeFields;
    });
}
```

#### Example with transforming data structure as a computed value:
```js
computed: {
	themeFields() {
		// basic deprecated structure
		const themeFields = {
			unordered: {
			label: "Misc",
			sections: []
			}
		};
		
		// transform new structure to deprecated structure
		Object.entries(this.structuredThemeFields.tabs).forEach(([tabName, tabValue]) => {
			Object.entries(tabValue.blocks).forEach(([blockName, blockValue]) => {
				Object.entries(blockValue.sections).forEach(([sectionName, sectionValue]) => {
					Object.entries(sectionValue.fields).forEach(([fieldName, fieldValue]) => {
						if (!themeFields[blockName]) {
							themeFields[blockName] = { label: sectionName, sections: {} }
						}

						if (!themeFields[blockName].sections[sectionName]) {
							themeFields[blockName].sections[sectionName] = { label: blockName }
						}

						themeFields[blockName].sections[sectionName][fieldName] = fieldValue;
					})
				})
			})
		});

		return themeFields;
	}
}
```
