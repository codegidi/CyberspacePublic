<?php
class ControllerExtensionPaymentCyberpay extends Controller
{
    public function index()
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/cyberpay');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['key'] = $this->config->get('payment_cyberpay_intergration_key');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $data['currency'] = $order_info['currency_code'];
        $data['ref']      = uniqid('' . $this->session->data['order_id'] . '-');
        $data['amount']   = intval($order_info['total'] * 100);
        $data['email']    = $order_info['email'];
        $data['callback'] = $this->url->link('extension/payment/cyberpay/callback');

        //Register Transaction
        $split = array('WalletId' => "teargstd",
        'Amount' => intval($data['amount'] * 100),
        'ShouldDeductFrom' => True);
        
        $fields = array('Currency' => "NGN",
            'MerchantRef' => $data['ref'],
            'Amount' => intval($data['amount'] * 100),
            'Description' => "Payment from  Opencart",
            'CustomerName' =>  $order_info['payment_firstname'],
            'CustomerEmail' => $order_info['email'],
            'CustomerMobile' => $order_info['telephone'],
            'IntegrationKey' => $data['key'],
            'ReturnUrl' => $data['callback'],
            'SharedSplits' => array($split));

        $payload = json_encode($fields);

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://payment-api.cyberpay.ng/api/v1/payments",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array("Content-Type: application/json")));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "cURL Error #:" . $err;
        } else {
            $responseObject = json_decode($response);
          
            if ($responseObject->code == "PY00") {
                $redirect_url = $responseObject->data->redirectUrl;
                $error = false;
                $error_detail = $responseObject->data->redirectUrl;
            } else {
                $error = true;
                $error_detail = 'Register transaction error: ' . $response;
            };
            
            $data['cp_error'] = $error;
            $data['cp_errorDetail'] = $error_detail;
            $data['cp_redirectUrl'] = $redirect_url;
        }

        return $this->load->view('extension/payment/cyberpay', $data);
    } 

    private function query_api_transaction_verify($reference)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://payment-api.cyberpay.ng/api/v1/payments/". $reference,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_POSTFIELDS => "",
        CURLOPT_HTTPHEADER => array("Content-Type: application/json")));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
            exit;
        } else {
            $responseObject = json_decode($response, true);
            return $responseObject;
        }
    }

    private function redir_and_die($url, $onlymeta = false)
    {
        if (!headers_sent() && !$onlymeta) {
            header('Location: ' . $url);
        }
        echo "<meta http-equiv=\"refresh\" content=\"0;url=" . addslashes($url) . "\" />";
        die();
    }

    public function callback()
    {
        if (isset($this->request->get['ref'])) {
            $trxref = $this->request->get['ref'];

            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            if ($order_info) {

                // Callback cyberpay to get real transaction status
                $ps_api_response = $this->query_api_transaction_verify($trxref);
                $order_status_id = $this->config->get('config_order_status_id');

                if($ps_api_response['code'] == "PY00") {
                    $order_status_id = $this->config->get('payment_cyberpay_order_status_id');
                    $redir_url = $this->url->link('checkout/success');
                } else {
                    $order_status_id = $this->config->get('payment_cyberpay_declined_status_id');
                    $redir_url = $this->url->link('checkout/checkout', '', 'SSL');
                }

                $this->model_checkout_order->addOrderHistory($order_id, $order_status_id);
                $this->redir_and_die($redir_url);
            }
        }

    }
}
