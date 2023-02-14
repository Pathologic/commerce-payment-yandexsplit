<?php

namespace Commerce\Payments;

class YandexSplitPayment extends Payment
{
    protected $debug = false;

    public function __construct($modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->lang = $modx->commerce->getUserLanguage('yandexsplit');
        $this->debug = $this->getSetting('debug') == '1';
    }

    public function getMarkup()
    {
        $settings = ['client_id', 'secret_key'];
        foreach ($settings as $setting) {
            if (empty($setting)) {
                return '<span class="error" style="color: red;">' . $this->lang['yandexsplit.error.error_empty_params'] . '</span>';
            }
        }

        return '';
    }

    public function getPaymentLink()
    {
        $processor = $this->modx->commerce->loadProcessor();
        $order = $processor->getOrder();
        $currency = ci()->currency->getCurrency($order['currency']);
        $payment = $this->createPayment($order['id'],
            ci()->currency->convertToDefault($order['amount'], $currency['code']));

        $data = [
            'order_meta' => [
                'checkout_redirect' => [
                    'on_fail'    => MODX_SITE_URL . 'commerce/yandexsplit/payment-failed',
                    'on_success' => MODX_SITE_URL . 'commerce/yandexsplit/payment-process?' . http_build_query([
                            'paymentId'   => $payment['id'],
                            'paymentHash' => $payment['hash'],
                        ]),
                ],
                'external_id'       => $payment['hash']
            ],
            'services'   => [
                [
                    'amount'   => number_format($payment['amount'], 2, '.', ''),
                    'currency' => 'RUB',
                    'type'     => 'loan'
                ]
            ]
        ];


        if ($this->debug) {
            $this->modx->logEvent(0, 1, 'Request data: <pre>' . print_r($data, true) . '</pre>',
                'Commerce YandexSplit Payment Debug: payment start');
        }
        $result = $this->request('order/create', $data);
        if (is_array($result) && isset($result['checkout_url'])) {
            $payment['meta'] = [
                'split_id' => $result['order_id']
            ];
            $processor->savePayment($payment);

            return $result['checkout_url'];
        } elseif ($this->debug) {
            $this->modx->logEvent(0, 3, 'Link is not received', 'Commerce YandexSplit Payment');
        }

        return false;
    }

    public function handleCallback()
    {
        $hash = $this->getRequestPaymentHash();
        if ($hash) {
            $processor = ci()->commerce->loadProcessor();
            $payment = $processor->loadPaymentByHash($hash);
            if ($payment && isset($_REQUEST['paymentId']) && $_REQUEST['paymentId'] == $payment['id'] && isset($payment['meta']['split_id'])) {
                $result = $this->request('order/info', [
                    'order_id' => $payment['meta']['split_id'],
                ]);
                if ($this->debug) {
                    $this->modx->logEvent(0, 1, 'Request data: <pre>' . print_r($result, true) . '</pre>',
                        'Commerce YandexSplit Payment Debug: payment check');
                }
				$events = ['approved', 'completed'];
                if (is_array($result) && isset($result['plan']['status']) && in_array($result['plan']['status'], $events)) {
                    try {
                        $order = $processor->loadOrder($payment['order_id']);
                        $processor->processPayment($payment, ci()->currency->convert($order['amount'], 'RUB', $order['currency']));
                        
                        $this->modx->sendRedirect(MODX_BASE_URL . 'commerce/yandexsplit/payment-success?paymentHash=' . $hash);
                    } catch (\Exception $e) {
                        $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(),
                                'Commerce YandexSplit Payment');
                    }
                }
            }
        }

        return false;
    }

    protected function getToken()
    {
        $clientId = $this->getSetting('client_id');
        $secretKey = $this->getSetting('secret_key');
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://oauth.yandex.ru/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            "client_id={$clientId}&client_secret={$secretKey}&grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result) {
            $result = json_decode($result, true);

            $result = $result['access_token'];
        }

        return $result;
    }


    protected function request($method, $data)
    {
        $url = $this->getSetting('test') == 1 ? 'https://split-api.tst.yandex.net/b2b/' : 'https://split-api.yandex.net/b2b/';
        $url .= $method;

        $token = $this->getToken();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if($method == 'order/create') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ]);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token
            ]);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        if ($this->debug) {
            $this->modx->logEvent(0, 1, "Response data: <pre>" . htmlentities(print_r($result, true)) . "</pre>",
                'Commerce YandexSplit Payment Debug: request');
        }

        return json_decode($result, true);
    }

    public function getRequestPaymentHash()
    {
        if (isset($_REQUEST['paymentHash']) && is_scalar($_REQUEST['paymentHash'])) {
            return $_REQUEST['paymentHash'];
        }

        return null;
    }
}
