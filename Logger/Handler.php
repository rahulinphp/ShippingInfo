<?php

namespace Hardwoods\ShippingInfo\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * Log file name
     * @var string
     */
    protected $fileName = '/var/log/shipping_estimate.log';
}
