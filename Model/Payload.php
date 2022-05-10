<?php

namespace Trellis\Salsify\Model;

use Magento\Framework\Model\AbstractModel;
use Trellis\Salsify\Api\Data\PayloadInterface;
use Trellis\Salsify\Model\ResourceModel\Payload as PayloadResource;

class Payload extends AbstractModel implements PayloadInterface
{
    public function _construct()
    {
        $this->_init(PayloadResource::class);
    }

    public function getId()
    {
        return $this->getData(PayloadInterface::ENTITY_ID);
    }

    public function setId($id)
    {
        $this->setData(PayloadInterface::ENTITY_ID, $id);
    }
}
