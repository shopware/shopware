<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write\Validation;

class RestrictDeleteViolation
{
    /**
     * Contains the
     *
     * @var string
     */
    protected $id;

    /**
     * Contains an array which indexed by definition class.
     * Each value represents a single restricted identity
     *
     * Example:
     *      [Shopware\Api\Shop\Definition\ShopDefinition] => Array
     *          (
     *              [0] => c708bb9d-c2c3-4243-b9fb-1fd6a676f2fb
     *              [1] => c708bb9d-c2c3-4243-b9fb-1fd6a676f2fb
     *          )
     *      [Shopware\Content\Product\Definition\ProductDefinition] => Array
     *          (
     *              [0] => c708bb9d-c2c3-4243-b9fb-1fd6a676f2fb
     *              [1] => c708bb9d-c2c3-4243-b9fb-1fd6a676f2fb
     *          )
     *
     * @var array[]
     */
    protected $restrictions;

    public function __construct(string $id, array $restrictions)
    {
        $this->id = $id;
        $this->restrictions = $restrictions;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRestrictions(): array
    {
        return $this->restrictions;
    }
}
