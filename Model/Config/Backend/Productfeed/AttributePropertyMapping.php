<?php

namespace Trellis\Salsify\Model\Config\Backend\Productfeed;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value as ConfigValue;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class AttributePropertyMapping
 */
class AttributePropertyMapping extends ConfigValue
{
    /**
     * @var Random
     */
    protected $mathRandom;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param Random $mathRandom
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param array $data
     * @param Json $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        Random $mathRandom,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
        Json $serializer = null
    ) {
        $this->mathRandom = $mathRandom;
        $this->serializer = $serializer ?: ObjectManager::getInstance()
            ->get(Json::class);
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Prepare data before save
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        unset($value['__empty']);
        $result = [];
        foreach ($value as $data) {
            if (empty($data['attribute']) || empty($data['property'])) {
                continue;
            }
            $attribute = $data['attribute'];
            if (array_key_exists($attribute, $result)) {
                // todo: address multiple same attributes set.
                try {
                    $result[$attribute] = $this->appendUniqueAttributes($result, [$data['property']]);
                } catch (\Exception $e) {

                }
            } else {
                $result[$attribute] = $data['property'];
            }
        }
        $this->setValue($this->serializer->serialize($result));
        return $this;
    }

    /**
     * Process data after load
     *
     * @return $this
     * @throws LocalizedException
     */
    public function afterLoad()
    {
        if ($this->getValue()) {
            $value = $this->serializer->unserialize($this->getValue());
            if (is_array($value)) {
                $this->setValue($this->encodeArrayFieldValue($value));
            }
        }
        return $this;
    }

    /**
     * Encode value to be used in \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
     *
     * @param array $value
     * @return array
     * @throws LocalizedException
     */
    protected function encodeArrayFieldValue(array $value): array
    {
        $result = [];
        foreach ($value as $attribute => $property) {
            $id = $this->mathRandom->getUniqueHash('_');
            $result[$id] = ['attribute' => $attribute, 'property' => $property];
        }
        return $result;
    }

    /**
     * Append unique countries to list of exists and reindex keys
     *
     * @param array $propertyList
     * @param array $inputAttributeList
     * @return array
     */
    private function appendUniqueAttributes(array $propertyList, array $inputAttributeList): array
    {
        $result = array_merge($propertyList, $inputAttributeList);
        return array_values(array_unique($result));
    }
}