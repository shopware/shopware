<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

trait AssignArrayTrait
{
    public function assign(array $options)
    {
        foreach ($options as $key => $value) {
            try {
                $this->$key = $value;
            } catch (\TypeError $error) {
                throw $error;
            } catch (\Error | \Exception $error) {
            }
        }

        return $this;
    }
}
