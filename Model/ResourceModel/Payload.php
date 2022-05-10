<?php

namespace Trellis\Salsify\Model\ResourceModel;

class Payload extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('salsify_webhook_payload', 'entity_id');
    }
}
