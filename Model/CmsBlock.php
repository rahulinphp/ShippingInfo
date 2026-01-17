<?php

namespace Hardwoods\ShippingInfo\Model;

use Hardwoods\ShippingInfo\Api\CmsBlockInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Hardwoods\ShippingInfo\Logger\Logger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Cms\Model\Template\FilterProvider;
use Hardwoods\ShippingInfo\Model\Config;

/**
 * Class CmsBlock
 * Retrieves CMS block content by identifier.
 */
class CmsBlock implements CmsBlockInterface
{
    /**
     * @var BlockRepositoryInterface
     */
    public $blockRepository;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var FilterProvider
     */
    public $filterProvider;

    /**
     * @var Config
     */
    public $config;

    /**
     * CmsBlock constructor.
     *
     * @param BlockRepositoryInterface $blockRepository
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     * @param FilterProvider $filterProvider
     * @param Config $config
     */
    public function __construct(
        BlockRepositoryInterface $blockRepository,
        StoreManagerInterface $storeManager,
        Logger $logger,
        FilterProvider $filterProvider,
        Config $config
    ) {
        $this->blockRepository = $blockRepository;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->filterProvider = $filterProvider;
        $this->config = $config;
    }

    /**
     * Get CMS block content by identifier.
     *
     * @param string $blockIdentifier The identifier of the CMS block.
     * @return string/null The content of the CMS block, or null if not found.
     */
    public function getBlockContentByIdentifier($blockIdentifier): string|null
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();

            if (!$this->config->canShowShippingInfoOnCheckoutPage($storeId)) {
                return null;
            }

            $block = $this->blockRepository->getById($blockIdentifier);
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
