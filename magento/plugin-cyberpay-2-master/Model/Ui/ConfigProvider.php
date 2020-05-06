<?php
namespace Pstk\Paystack\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\Store as Store;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'mdt_cyberpay';

    protected $method;

    public function __construct(PaymentHelper $paymentHelper, Store $store)
    {
        $this->method = $paymentHelper->getMethodInstance(self::CODE);
        $this->store = $store;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $intergration_key = $this->method->getConfigData('intergration_key');
        
        return [
            'payment' => [
                self::CODE => [
                    'intergration_key' => $intergration_key,
                    'return_url' => $this->store->getBaseUrl() . 'rest/'
                ]
            ]
        ];
    }
}
