<?php

namespace Hardwoods\ShippingInfo\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Escaper;
use Hardwoods\ShippingInfo\Logger\Logger;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Cms\Model\Template\FilterProvider;
use Hardwoods\ShippingInfo\Model\Config;

class Information extends Template
{

    /**
     * Static block for shipping information
     */
    public const BLOCK_IDENTIFIER = 'shipping_description_product_page';

    /**
     * @var Escaper
     */
    public $escaper;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var BlockRepositoryInterface
     */
    public $blockRepository;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    
    /**
     * @var FilterProvider
     */
    public $filterProvider;

    /**
     * @var Config
     */
    public $config;

    /**
     * Construct function
     *
     * @param Template\Context $context
     * @param Escaper $escaper
     * @param Logger $logger
     * @param BlockRepositoryInterface $blockRepository
     * @param StoreManagerInterface $storeManager
     * @param FilterProvider $filterProvider
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Escaper $escaper,
        Logger $logger,
        BlockRepositoryInterface $blockRepository,
        StoreManagerInterface $storeManager,
        FilterProvider $filterProvider,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->escaper = $escaper;
        $this->logger = $logger;
        $this->blockRepository = $blockRepository;
        $this->storeManager = $storeManager;
        $this->filterProvider = $filterProvider;
        $this->config = $config;
    }

    /**
     * Get shipping information
     *
     * @return string
     */
    public function getInfo()
    {
        try {

            $storeId = $this->storeManager->getStore()->getId();

            if (!$this->config->canShowShippingInfoOnPdpPage($storeId)) {
                return;
            }

            $identifier = self::BLOCK_IDENTIFIER;
            $block = $this->blockRepository->getById($identifier);
            
            $storeIds = $block->getStores();

            if ($block->isActive() && (in_array(0, $storeIds) || in_array($storeId, $storeIds))) {
                $processedContent = $this->filterProvider->getBlockFilter()->filter($block->getContent());
                return $processedContent;
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return null;
    }
}
