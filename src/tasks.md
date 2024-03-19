# General
- [ ] Rector rules for easy update
- [ ] Upgrade guide

# Context rules
- [ ] Rules are no longer available in context object
- [ ] Rewrite ActiveRulesDataCollectorSubscriber and show rules of the cart (if cart was loaded) > maybe via event + listener?
- [ ] Remove duplicate scope CartRuleScope/CheckoutRuleScope 

# Cart rules
- [ ] Rules in cart will be validated after each processor
- [ ] Attach matching rules to cart
- [ ] Adapt classes in cart domain which rely on context.rules and change it to cart.rules
- [ ] Remove cart rule loader
- [ ] Service dependency tree for cart services is heavy, try to reduce it and simplify it (maybe reverse route<->service pattern here)

# product pricing api
- [ ] New php class to load the product prices
- [ ] Different scopes listing/pdp/cart
- [ ] New pricing concept (customer group, sales channel, delivery_country?)
- [ ] Rework of cheapest price updater
- [ ] rework the cheapest price accessor builder in sql
- [ ] Rework the cheapest price in elasticsearch
- [ ] Price twig templates and export templates

# http cache 
- [ ] Remove rules from a cache key
- [ ] Dispatch event to calculate http cache key
- [ ] Add a cookie list to allow extending the cache-key
- [ ] Catch event in CacheStore and Reverse proxy
- [ ] Remove cache decorator
- [ ] Rework cache invalidator, to optimize tags and hit invalidation count
- [ ] Make frontend.home.page cacheable again

# Store api
- [ ] Use new `AddCacheTagEvent` event to add tags to http cache
- [ ] Store api full page cache
- [ ] Remove cached routes
- [ ] I would like to remove abstract routes and integrate new event-decoration pattern 

# delay cache 
- [ ] Activate delay cache by default
- [ ] Delay everything

# Esi integration
- [ ] Header and footer via esi 
- [ ] header pagelet no more in page
- [ ] Replace activeLanguage 
- [ ] Replace activeCurrency
- [ ] Replace minSearchLength
- [ ] Cms category sidebar element own resolver
- [ ] Replace active navigation id
- [ ] Add http cache support esi requests
- [ ] Breadcrumb via id
- [ ] Remove header from page object
- [ ] Remove footer from page object

# Price and stock api
- [ ] provide new http api endpoint to import product prices
- [ ] instant purge on http cache for out-of-stock/price-changed products

# Flow improvement
- [ ] Flow can no more rely on context.ruleIds
- [ ] Should also not rely on order object loaded
- [ ] Rules in flows should be allowed to query data by their own
- [ ] Remove big data objects from flow data and migrate rules to new rule system
- [ ] Dont restore context / order in flows to evaluate rules
- [ ]

# Theme caching
- [ ] Rework theme caching to have one tag for all theme related stuff because of shared on each page
- [ ] Includes theme config, theme assignment, snippets, etc

---

# Nice to have

# Twig rendering performance
- [ ] Improve filter panel rendering
- [ ] Improve product list rendering
- [ ] Improve navigation rendering

## Document storage
- [ ] Require new document storage as composer dependency
- [ ] Use document storage for product listing > At least minimal data stack for filter
- [ ] Use document storage for navigation > At least minimal data stack for navigation 

## Decorator extension
- [ ] Introduce new decoration pattern via event

## Documentation system
- [ ] Introduce new documentation system
- [ ] Added documentation for new store-api route decorations
