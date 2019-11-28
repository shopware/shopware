<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Steps;

class ResultMapper
{
    /**
     * @param ValidResult|FinishResult|ErrorResult $result
     *
     * @throws \Exception
     *
     * @return array
     */
    public function toExtJs($result)
    {
        if ($result instanceof ValidResult) {
            return [
                'valid' => true,
                'offset' => $result->getOffset(),
                'total' => $result->getTotal(),
                'success' => true,
            ];
        }

        if ($result instanceof FinishResult) {
            return [
                'valid' => false,
                'offset' => $result->getOffset(),
                'total' => $result->getTotal(),
                'success' => true,
            ];
        }

        if ($result instanceof ErrorResult) {
            return [
                'valid' => false,
                'errorMsg' => $result->getMessage(),
            ];
        }

        throw new \Exception(sprintf('Result type %s can not be mapped.', get_class($result)));
    }
}
