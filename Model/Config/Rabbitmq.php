<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */
namespace Trellis\Salsify\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Rabbitmq
{
    private const XML_RABBITMQ_ENABLED_PATH = 'trellis_salsify/rabbitmq/enabled';

    private const XML_RABBITMQ_HOST_PATH = 'trellis_salsify/rabbitmq/host';

    private const XML_RABBITMQ_PORT_PATH = 'trellis_salsify/rabbitmq/port';

    private const XML_RABBITMQ_USER_PATH = 'trellis_salsify/rabbitmq/user';

    private const XML_RABBITMQ_PASS_PATH = 'trellis_salsify/rabbitmq/password';

    private const XML_RABBITMQ_VHOST_PATH = 'trellis_salsify/rabbitmq/vhost';

    private const XML_RABBITMQ_PAGINATION_PATH = 'trellis_salsify/rabbitmq/pagination';

    public const QUEUE_NAME = 'salsify_qos_queue';

    /**
     * @var EncryptorInterface
     */
    private $encryptor;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->encryptor = $encryptor;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param null $store
     * @return bool
     */
    public function isEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_RABBITMQ_ENABLED_PATH, 'store', $store);
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getHost($store = null)
    {
        return $this->scopeConfig->getValue(self::XML_RABBITMQ_HOST_PATH, 'store', $store);
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getPort($store = null)
    {
        return $this->scopeConfig->getValue(self::XML_RABBITMQ_PORT_PATH, 'store', $store);
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getUser($store = null)
    {
        return $this->scopeConfig->getValue(self::XML_RABBITMQ_USER_PATH, 'store', $store);
    }

    /**
     * @param null $store
     * @return string
     */
    public function getPassword($store = null)
    {
        $value = $this->scopeConfig->getValue(self::XML_RABBITMQ_PASS_PATH, 'store', $store);
        return $this->encryptor->decrypt($value);
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getVhost($store = null)
    {
        return $this->scopeConfig->getValue(self::XML_RABBITMQ_VHOST_PATH, 'store', $store);
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getPagination($store = null)
    {
        return $this->scopeConfig->getValue(self::XML_RABBITMQ_PAGINATION_PATH, 'store', $store);
    }
}
