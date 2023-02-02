[titleEn]: <>(Template Filter)
[hash]: <>(article:storefront_template_filter)

## sw_sanitize
Filters tags and attributes from given variable.

The filter can be found in 
[`/src/Storefront/Framework/Twig/Extension/SwSanitizeTwigFilter.php`](https://github.com/shopware/platform/blob/master/src/Storefront/Framework/Twig/Extension/SwSanitizeTwigFilter.php)

### Usage
`{{ unfilteredHTML|sw_sanitize }}` : Uses the default config
  
  
`{{ unfilteredHTML|sw_sanitize(mixed options = null, bool override = false) }}`

1. options: 
    - tag => attribute array that is specifically allowed
    - `*` as tag = all tags
2. override: 
    - true => uses the options as the config
    - false (default) => merges the default config with the options 

### Examples
`{{ unfilteredHTML|sw_sanitize }}` 
  : Uses the default config
  
***

`{{ unfilteredHTML|sw_sanitize( {'div': ['style', ...]}, true ) }}`
  : **allow only** div tags + style attribute for div

`{{ unfilteredHTML|sw_sanitize( {'div': ['style', ...]} ) }}`
  : **merge** options into default config

***

`{{ unfilteredHTML|sw_sanitize( {'*': ['style', ...]}, true ) }}` 
  : **won't work** because there are no tags 

`{{ unfilteredHTML|sw_sanitize( {'*': ['style', ...]} ) }}` 
  : **merge** options into default config 
  
***
  
`{{ unfilteredHTML|sw_sanitize( {'div': ['class'], '*': ['style', ...]}, true ) }}`
  : **allow only** div tags + class attribute + allow style attribute for all tags
  
`{{ unfilteredHTML|sw_sanitize( {'div': ['class'], '*': ['style', ...]} ) }}`
  : **merge** options into default config
  
***

`{{ unfilteredHTML|sw_sanitize('', true) }}`
  : **special case :** filters all tags and attributes, because there are no allowed tags or attributes 
    - override => true, empty options array
