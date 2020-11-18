# 2020-08-10 - Feature flag system

## Context
To provide a way to toggle code from incomplete features, the feature flag system was implemented.
Because of the high turnover on code for features in development, the system needs to be robust and easy to use.
Also it is recommended that the system is safe to not breaking things when a flag is not completely provided.

## Decision
* A feature flag will be added to the system by adding it to the file ```/Core/Framework/Resources/config/packages/shopware.yaml```
```yaml
shopware:
    ....
    feature:
        flags:
            - NEXT-733
            - FEATURE-NEXT-1797
            - FEATURE_NEXT_1797
```
* The flag should have a reference to an issue.
* The full flag name will always be ```FEATURE_XXX_XXX``` while the ```FEATURE_``` prefix is hard coded and can be provided in the configuration, but doesn't have to (see examples above).
* Inside the code the flag will always be noted with its full name ```FEATURE_XXX_XXX```
* After the feature is completed, the config of the flag and every note in the code have to be deleted with the merge request that also deletes possible old code which was replaced by the new feature.  

## Consequences
From now on, every new feature has to have a feature flag from the first merge into master (or any other releasable branch). 

## Counter decisions
The way the flags now work is very lax. It doesn't prevent typos or lost code by itself. The developer have to be neat to be sure to use the correct flag name and find every occurrence of the flag while deleting it.
Another possibility would be to provide the flags as php-classes. This attempt was discarded because of the overhead.
