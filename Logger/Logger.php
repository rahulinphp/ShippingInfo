<?php

namespace Hardwoods\ShippingInfo\Logger;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Monolog\Logger as MonologLogger;

class Logger extends MonologLogger
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Config path for debug logging
     */
    public const XML_PATH_DEBUG_ENABLED = 'products/shipping_estimate/debug';

    /**
     * Construct function
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param string $name
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        $name,
        array $handlers = [],
        array $processors = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Debug message function
     *
     * @param string $message The log message
     * @param mixed[] $context The log context
     * @return void
     */
    public function debug($message, array $context = []): void
    {
        $storeId = $this->getStoreId();

        $debugEnabled = $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($debugEnabled) {
            parent::debug($message, $context);
        }
    }

    /**
     * Get current store ID safely
     *
     * @return int
     */
    protected function getStoreId(): int
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();
            return $storeId ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
