<?php

namespace Hardwoods\ShippingInfo\Model;

use Magento\Store\Model\ScopeInterface;

class Config
{
    /**
     * Config path for show shipping info on product detail page.
     */
    public const  XML_PATH_SHIPPING_INFO_PDP = 'products/shipping_information/show_on_product_detail_page';

    /**
     * Config path for show shipping info on checkout page.
     */
    public const  XML_PATH_SHIPPING_INFO_CHECKOUT = 'products/shipping_information/show_on_checkout_page';

    /**
     * @var Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Verify is config value set
     *
     * @param string $configPath
     * @param int $storeId
     * @return boolean
     */
    public function isValueSet($configPath, $storeId)
    {
        return $this->scopeConfig->isSetFlag(
            $configPath,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get config value
     *
     * @param string $configPath
     * @param int $storeId
     * @return int/string
     */
    public function getConfigValue($configPath, $storeId)
    {
        return $this->scopeConfig->getValue(
            $configPath,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check shipping info enabled for pdp page
     *
     * @param int $storeId
     * @return bool
     */
    public function canShowShippingInfoOnPdpPage(int $storeId = 0): bool
    {
        return $this->isValueSet(self::XML_PATH_SHIPPING_INFO_PDP, $storeId);
    }

    /**
     * Check shipping info enabled for checkout page
     *
     * @param int $storeId
     * @return bool
     */
    public function canShowShippingInfoOnCheckoutPage(int $storeId = 0): bool
    {
        return $this->isValueSet(self::XML_PATH_SHIPPING_INFO_CHECKOUT, $storeId);
    }
}
