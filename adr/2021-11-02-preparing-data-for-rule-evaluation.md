# 2021-11-02 - Preparing data for rule evaluation

## Context

When we are creating new `Rule` definitions, we need to consider how we are retrieving the data necessary for the evaluation.
Since rules could possibly be evaluated on any given request, the data for the evaluation must also be present at all times.
This circumstance causes us to carefully consider the performance with regards to additional database queries or storing the data
beforehand.

## Decision

Instances of `Rule` should always be able to evaluate based on data retrieved from an instance of `RuleScope`. This instance
provides getters for `Context`, `SalesChannelContext` and an instance of `\DateTimeImmutable` as the current time. Everything
that needs to be evaluated should be derived from methods of these instances.

When the data necessary for evaluation **can't** already be retrieved by the methods of `Context` and `SalesChannelContext`,
the **least** favorable option should be to add additional associations to the `Criteria` of DAL searches, e.g. in the `SalesChannelContextFactory`.
Unless an additional association is needed anyways and in a much wider scope, the preferred option should always be to use indexers and updater services. 
Using the indexers, only the data absolutely necessary for evaluation can be stored as part of the target entity's definition.
As the data is persisted asynchronously within the message queue, it should be kept up to date by background processes and we can avoid any additional database queries
during storefront requests.

## Consequences

### Make sure that the `RuleScope` doesn't already provide you with the necessary data to evaluate the rule

* If you're trying to match whether the target entity has a specific "ManyToOne" or a "OneToOne" association, use the corresponding `FkField` of the target's definition
  to match the id of the association.
* If you're trying to match whether the target entity has a specific "ManyToMany" association, use the corresponding `ManyToManyIdField`. 
  If there is no `ManyToManyIdField` for the the association yet and you can evaluate the rule by matching the id of the association, you should
  always prefer adding a `ManyToManyIdField` to the definition before seeking alternative solutions. If you are adding a `ManyToManyIdField`
  make sure that the target entity has an indexer and that it calls the `ManyToManyIdFieldUpdater`.
  
### Writing indexer/updater services for rule evaluation

If you absolutely need data other than the id from an association for the rule evaluation, you should create or use an existing service for indexing said
data. Consider the following when writing the service for updating indexed fields:

* If the indexed data contains multiple values per entity use a `JsonField` to store it in.
* The updater service should use plain SQL only.
* Make sure the updater service can handle updating a great number of rows at once at the best possible performance.
* Make sure the updater service is also called and updates accordingly on deletion of data upon which the index data is based on.
* If the indexed data also relies on the state of the target entity, make sure the updater service is also called on changes to the target entity and can update the indexed data accordingly.
* If the data to be indexed can be inherited from a parent make sure to also consider that by involving the parent ids in the plain SQL that updates the field containing pre-indexed data.
