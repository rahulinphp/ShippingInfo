<?php

declare(strict_types=1);

namespace Hardwoods\ShippingInfo\Controller\Shipping;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\RequestException;
use Hardwoods\ShippingInfo\Logger\Logger;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class Estimate implements HttpPostActionInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var PricingHelper
     */
    private PricingHelper $pricingHelper;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;

    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param PricingHelper $pricingHelper
     * @param StoreManagerInterface $storeManager
     * @param CheckoutSession $checkoutSession
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ClientFactory $clientFactory
     * @param Logger $logger
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        PricingHelper $pricingHelper,
        StoreManagerInterface $storeManager,
        CheckoutSession $checkoutSession,
        RequestInterface $request,
        ResultFactory $resultFactory,
        ClientFactory $clientFactory,
        Logger $logger
    ) {
        $this->productRepository = $productRepository;
        $this->pricingHelper = $pricingHelper;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
    }

    /**
     * Execute the action.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $params = $this->request->getParams();
        $storeId = (int) $this->storeManager->getStore()->getId();
        $baseUrl = $this->storeManager->getStore($storeId)->getBaseUrl();

        if (empty($params['zipcode'])) {
            return $this->jsonResponse([
                'success' => false,
                'message' => __('Please enter zip code.'),
                'error' => __('Please enter zip code.')
            ]);
        }

        if (empty($params['product_id']) || !is_numeric($params['product_id'])) {
            $this->logger->error(__('Product ID is invalid.'));
            return $this->jsonResponse([
                'success' => false,
                'error' => __('Product ID is invalid.')
            ]);
        }

        $qty = (!empty($params['qty']) && is_numeric($params['qty'])) ? (int) $params['qty'] : 1;
        $zipcode = $params['zipcode'];
        $productId = (int) $params['product_id'];
        $childProductId = (int) $params['child_product_id'] ?? 0;

        if ($this->checkoutSession->getQuote()->getShippingAddress()->getPostcode() === '') {
            $this->checkoutSession->setDefaultPostalCode($zipcode);
        }

        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
            $sku = $product->getSku();

            $guestCartToken = $this->sendRequest($baseUrl . 'rest/V1/guest-carts/');
            $guestCartToken = str_replace('"', '', trim((string) $guestCartToken));

            if (!$guestCartToken) {

                return $this->jsonResponse([
                    'success' => false,
                    'error' => __('Could not create guest cart.')
                ]);
            }

            $itemData = [
                'cartItem' => [
                    'sku' => $sku,
                    'qty' => $qty,
                ]
            ];

            if ($product->getTypeId() === 'configurable') {
                $itemData['cartItem']['product_option']['extension_attributes']['configurable_item_options'] =
                    $this->getConfigurableOptions($product, $storeId, $childProductId);
            }

            $this->sendRequest(
                $baseUrl . 'rest/V1/guest-carts/' . $guestCartToken . '/items',
                $itemData
            );

            $shippingAddress = [
                'address' => [
                    'country_id' => 'US',
                    'postcode' => $zipcode,
                ]
            ];

            $shippingResponse = $this->sendRequest(
                $baseUrl . 'rest/V1/guest-carts/' . $guestCartToken . '/estimate-shipping-methods',
                $shippingAddress
            );

            $shippingMethods = json_decode((string) $shippingResponse, false);
            $result = $this->formatShippingRates($shippingMethods);

            $totals = [];

            $flooringTotal = (float) $this->getFlooringTotal($productId, $qty, $storeId, $childProductId);
            $totals['flooring_total'] = $this->pricingHelper->currency((float) $flooringTotal, true, false);

            $finalProductPrice = $this->getFinalProductPrice($productId, $storeId, $childProductId);
            $totals['final_product_price'] = $this->pricingHelper->currency((float) $finalProductPrice, true, false);

            $shippingCost = 0.0;

            if (isset($result['shipping']['lowest']['cost'])) {
                $shippingCost = (float) filter_var(
                    $result['shipping']['lowest']['cost'],
                    FILTER_SANITIZE_NUMBER_FLOAT,
                    FILTER_FLAG_ALLOW_FRACTION
                );
            }

            $grandTotal = $flooringTotal + $shippingCost;
            $totals['grand_total'] = $this->pricingHelper->currency((float) $grandTotal, true, false);

            $result['totals'] = $totals;

            $this->logger->debug('Shipping Estimate: ' . json_encode($result));
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            $rawMessage = $e->getMessage();

            $userMessage = __('Something went wrong. Please try afterwards.');

            if (preg_match('/"message"\s*:\s*"([^"]+)"/', $rawMessage, $matches)) {
                $userMessage = $matches[1];
            }

            $this->logger->error(__('Estimate error: %1', $rawMessage));

            return $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $userMessage
            ]);
        }
    }

    /**
     * Calculate product flooring total.
     *
     * @param int $productId
     * @param int $qty
     * @param int $storeId
     * @param int $childProductId
     * @return string
     */
    public function getFlooringTotal(int $productId, int $qty, int $storeId, int $childProductId): string
    {
        $flooringTotal = 0.0;

        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
            $sqFtPerBox = (float) $product->getData('sq_ft_per_box');

            if ($sqFtPerBox > 0 && $qty > 0) {
                $totalSqFt = $sqFtPerBox * $qty;
                $productPrice = $this->getFinalProductPrice($productId, $storeId, $childProductId);
                $flooringTotal = $productPrice * $totalSqFt;
            }
        } catch (\Exception $e) {
            $this->logger->error(__('Flooring total error: %1', $e->getMessage()));
            $flooringTotal = 0.0;
        }

        return number_format($flooringTotal, 2, '.', '');
    }

    /**
     * Get final price of a product (simple or selected configurable variant).
     *
     * @param int $productId
     * @param int $storeId
     * @param int|null $childProductId
     * @return float
     */
    public function getFinalProductPrice(
        int $productId,
        int $storeId,
        ?int $childProductId = null
    ): float {
        try {
            $product = $this->productRepository->getById($productId, false, $storeId);

            // If product is configurable
            if ($product->getTypeId() === Configurable::TYPE_CODE) {
                if (!$childProductId) {
                    // Get associated simple products
                    $childProducts = $product->getTypeInstance()->getUsedProducts($product);

                    if (!empty($childProducts)) {
                        $childProduct = reset($childProducts);
                        $childProductId = (int) $childProduct->getId();
                    }
                }

                if ($childProductId) {
                    $childProduct = $this->productRepository->getById($childProductId, false, $storeId);
                    $price = $childProduct->getSpecialPrice() > 0
                        ? $childProduct->getSpecialPrice()
                        : $childProduct->getPrice();

                    return (float) number_format((float) $price, 2, '.', '');
                }
            }

            // For non-configurable or fallback
            $price = $product->getSpecialPrice() > 0
                ? $product->getSpecialPrice()
                : $product->getPrice();

            return (float) number_format((float) $price, 2, '.', '');
        } catch (\Exception $e) {
            $this->logger->error(
                __(
                    'Error while getting product price for product ID %1: %2',
                    $productId,
                    $e->getMessage()
                )
            );
            return 0.00;
        }
    }

    /**
     * Send HTTP request using Guzzle client.
     *
     * @param string $url
     * @param array $data
     * @return string|null
     * @throws LocalizedException
     */
    private function sendRequest(string $url, array $data = []): ?string
    {
        try {
            $client = $this->clientFactory->create();
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ];

            if (!empty($data)) {
                $options['body'] = json_encode($data);
            }

            $response = $client->request('POST', $url, $options);

            if (!in_array($response->getStatusCode(), [200, 201], true)) {
                throw new LocalizedException(
                    __('Request to %1 failed with status code %2.', $url, $response->getStatusCode())
                );
            }

            return (string) $response->getBody();
        } catch (RequestException $e) {
            $message = $e->hasResponse()
                ? (string) $e->getResponse()->getBody()
                : $e->getMessage();

            $this->logger->error(__('Guzzle error: %1', $message));
            throw new LocalizedException(__('Guzzle error: %1', $message));
        }
    }

    /**
     * Format shipping rates from API.
     *
     * @param array|null $shippingMethods
     * @return array
     */
    private function formatShippingRates(?array $shippingMethods): array
    {
        if (!is_array($shippingMethods) || empty($shippingMethods)) {
            return [
                'success' => false,
                'message' => __('No shipping methods available.')
            ];
        }

        $lowest = null;

        foreach ($shippingMethods as $method) {
            if (!isset($method->amount)) {
                continue;
            }

            if ($lowest === null || $method->amount < $lowest->amount) {
                $lowest = $method;
            }

            $allShippingMethodsFormatted[] = [
                'cost' => $this->pricingHelper->currency($method->amount, true, false),
                'carrier' => $method->carrier_title ?? $method->carrier_code,
                'method' => $method->method_title ?? $method->carrier_code
            ];
        }

        if ($lowest === null) {
            return [
                'success' => false,
                'message' => __('No valid shipping method found.')
            ];
        }

        return [
            'success' => true,
            'shipping' => [
                'lowest' => [
                    'cost' => $this->pricingHelper->currency($lowest->amount, true, false),
                    'carrier' => $lowest->carrier_title ?? $lowest->carrier_code,
                    'method' => $lowest->method_title ?? $lowest->carrier_code
                ],
                'all_methods' => $allShippingMethodsFormatted ?? []
            ],

            'success' => true,
        ];
    }

    /**
     * Retrieve configurable options for the selected or first available child product.
     *
     * @param ProductInterface $configurableProduct
     * @param int $storeId
     * @param int|null $childProductId
     * @return array
     */
    private function getConfigurableOptions(
        ProductInterface $configurableProduct,
        $storeId,
        ?int $childProductId = null
    ): array {
        $options = [];

        try {
            $childProducts = $configurableProduct->getTypeInstance()->getUsedProducts($configurableProduct);

            if (!$childProductId && count($childProducts)) {
                $childProductId = (int) $childProducts[0]->getId();
            }

            if (!$childProductId) {
                return $options;
            }

            $childProduct = $this->productRepository->getById($childProductId, false, $storeId);
            $usedAttributes = $configurableProduct->getTypeInstance()->getConfigurableAttributes($configurableProduct);

            foreach ($usedAttributes as $attribute) {
                $attributeId = (int) $attribute->getProductAttribute()->getId();
                $attributeCode = $attribute->getProductAttribute()->getAttributeCode();
                $optionValue = $childProduct->getData($attributeCode);

                if ($optionValue !== null) {
                    $options[] = [
                        'option_id' => $attributeId,
                        'option_value' => $optionValue
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(__('Error getting configurable options: %1', $e->getMessage()));
        }

        return $options;
    }

    /**
     * Return JSON result.
     *
     * @param array $data
     * @return ResultInterface
     */
    private function jsonResponse(array $data): ResultInterface
    {
        return $this->resultFactory
            ->create(ResultFactory::TYPE_JSON)
            ->setData($data);
    }
}
