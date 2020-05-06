<?php

namespace Pstk\Paystack\Model;

use Exception;
use Magento\Payment\Helper\Data as PaymentHelper;

class Payment implements \MDT\Codegidi\Api\PaymentInterface
{
    const CODE = 'mdt_codegidi';

    protected $config;

    protected $cyberpay;

    /**
     * @var EventManager
     */
    private $eventManager;

    public function __construct(
        PaymentHelper $paymentHelper,
        \Magento\Framework\Event\Manager $eventManager
    ) {
        $this->eventManager = $eventManager;
        $this->config = $paymentHelper->getMethodInstance(self::CODE);
        $this->$intergration_key = $this->config->getConfigData('intergration_key');
    }

    /**
     * @return bool
     */
    public function verifyPayment($ref_quote)
    {
        // we are appending quoteid
        $ref = explode('_-~-_', $ref_quote);
        $reference = $ref[0];
        $quoteId = $ref[1];
        try {
            $transaction_details = $this->paystack->transaction->verify([
                'reference' => $reference
            ]);
            if ($transaction_details->data->metadata->quoteId === $quoteId) {
                // dispatch the `payment_verify_after` event to update the order status
                $this->eventManager->dispatch('payment_verify_after');

                return json_encode($transaction_details);
            }
        } catch (Exception $e) {
            return json_encode([
                'status'=>0,
                'message'=>$e->getMessage()
            ]);
        }
        return json_encode([
            'status'=>0,
            'message'=>"quoteId doesn't match transaction"
        ]);
    }
}
