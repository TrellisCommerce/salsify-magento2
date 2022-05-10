<?php
namespace Trellis\Salsify\Controller\Hook;

use Magento\Framework\Exception\LocalizedException;

class Delete extends AbstractHook
{
    /**
     * @param $payload
     * @return mixed|void
     * @throws \Exception
     */
    public function hook($payload)
    {
        throw new LocalizedException(__('Not implemented!'));
    }
}
