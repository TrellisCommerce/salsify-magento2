<?php
namespace Trellis\Salsify\Model\ResourceModel\Payload;
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Trellis\Salsify\Model\Payload',
            'Trellis\Salsify\Model\ResourceModel\Payload'
        );
    }
}
