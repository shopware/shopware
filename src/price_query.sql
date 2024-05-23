
SELECT
    *
FROM product
    LEFT JOIN EK_shopA_DE as current
        ON prices.product_id = product.id
    LEFT JOIN prices as default
        ON prices.product_id = product.id
        AND group_id IS NULL
        AND channel_id IS NULL
        AND country_id IS NULL
        AND from_quantity = 1
;




SELECT *
FROM product
    # creating a new sales channel generates the table
    # fallback to default price till products got assigned
    # assign product will trigger indexing
    LEFT JOIN shop_a  #(DE is implicit because of default country)
        ON shop_a.product_id = product.id
        AND shop_a.group_id = 'EK' # each group contains an own record

    LEFT JOIN default_price
        ON default_price.product_id = product.id
;



SELECT *
FROM product
    LEFT JOIN shop_a
        ON shop_a.product_id = product.id
        AND shop_a.group_id = 'EK'

    LEFT JOIN default_price
        ON default_price.product_id = product.id
;


INSERT INTO shop_a.price
    (product_id, price)

SELECT product.id, COLESCE(
    prices.EK_shopA_DE,
    prices.EK_shopA,
    prices.EK
);



# expected we want to include the current selected shipping country

SELECT *
FROM product
    LEFT JOIN shop_a_country
        ON shop_a.product_id = product.id
        AND shop_a.group_id = 'EK' # each group contains an own record
        AND shop_a.country_id = 'DE'

    LEFT JOIN shop_a_country  #(DE is implicit because of default country)
        ON shop_a.product_id = product.id
        AND shop_a.group_id = 'EK' # each group contains an own record
        AND shop_a.country_id = 'default'

    LEFT JOIN default_price
        ON default_price.product_id = product.id
;



# single join for each constelation
SELECT *
FROM product

LEFT JOIN prices as 3_match
    ON prices.product_id = product.id
    AND group_id = 'EK'
    AND channel_id = 'A'
    AND country_id = 'DE'
    AND from_quantity = 1

LEFT JOIN prices as 2_match
  ON prices.product_id = product.id
      AND group_id = 'EK'
      AND channel_id = 'A'
      AND country_id IS NULL
      AND from_quantity = 1

LEFT JOIN prices as 1_match
   ON prices.product_id = product.id
   AND group_id = 'EK'
   AND channel_id IS NULL
   AND country_id IS NULL
   AND from_quantity = 1

LEFT JOIN prices as 0_match
    ON prices.product_id = product.id
    AND group_id IS NULL
    AND channel_id IS NULL
    AND country_id IS NULL
    AND from_quantity = 1

WHERE COLESCE(
    3_match.price,
    2_match.price,
    1_match.price
    0_match.price
) > 100

ORDER BY COLESCE(
    3_match.price,
    2_match.price,
    1_match.price
    0_match.price
)















default-price
    group-price
    group-channel-price


