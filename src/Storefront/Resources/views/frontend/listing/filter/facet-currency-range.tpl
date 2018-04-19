{extends file="parent:frontend/listing/filter/facet-range.tpl"}

{block name="frontend_listing_filter_facet_range_slider_config"}
    {$startMin = $facet->getActiveMin()}
    {$startMax = $facet->getActiveMax()}
    {$rangeMin = $facet->getMin()}
    {$rangeMax = $facet->getMax()}
    {$roundPretty = 'false'}
    {$format = "{'0'|currency}"}
    {$stepCount = 100}
    {$stepCurve = 'linear'}
{/block}