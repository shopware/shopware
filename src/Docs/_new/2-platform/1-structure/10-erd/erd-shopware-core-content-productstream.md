[titleEn]: <>(Product streams)

Product streams describe stored filter conditions that applied to the catalogue as a whole to create dynamic streams.

![Product streams](dist/erm-shopware-core-content-productstream.svg)


### Table `product_stream`

Product streams are a dynamic collection of products based on stored search filters. This is the root table representing these filters. *Attention: after creation, product streams need to be indexed, they can not be used until `invalid` is `false`*


### Table `product_stream_filter`

Represents a single filter property. All to a stream related filters build a persisted and nested search query.


