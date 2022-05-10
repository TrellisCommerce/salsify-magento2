<?php

namespace Trellis\Salsify\Helper;

/**
 * Class ReadinessReports
 * @package Trellis\Salsify\Helper
 */
class ReadinessReports extends Data
{
    public function getToken($store = null)
    {
        return '267dc922d77d646cad6f4d9abf3de8f85eb3823897c8017dd830dcf225e92191';
    }

    public function getOrganizationId($store = null)
    {
        return '12367';
    }

    public function getChannelId($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/readiness_reports/channel_id', 'store', $store);
    }

    public function getTargetSchemaName($store = null)
    {
        $companyName = $this->scopeConfig->getValue('trellis_salsify/readiness_reports/target_schema_name', 'store', $store);

        return 'trellis_plugin_' . str_replace(' ', '_', trim(strtolower($companyName))) . '_' . $this->_date->gmtDate('Y_m_d');
    }

    public function getTargetSchemaExternalId($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/readiness_reports/company_name', 'store', $store);
    }

    public function getTargetSchemaCallbackUrl($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/readiness_reports/target_schema_callback_url', 'store', $store);
    }

    public function getTargetSchemaServiceName($store = null)
    {
        return "TrellisConnectionService";
    }

    /*
     * Get attributes to export, unserialized.
     *
     * @param null $store
     *
     * @return array|bool|float|int|mixed|string|null
     */
    public function getTargetSchemaAttributes($store = null)
    {
        $value = $this->scopeConfig->getValue('trellis_salsify/readiness_reports/target_schema_attributes', 'store', $store);
        if (!empty($value)) {
            $value = $this->_serializer->unserialize($value);
        }

        return $value;
    }
}
