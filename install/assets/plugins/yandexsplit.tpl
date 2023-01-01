//<?php
/**
 * Payment YandexSplit
 *
 * YandexSplit payments processing
 *
 * @category    plugin
 * @version     1.0.0
 * @author      Pathologic
 * @internal    @events OnRegisterPayments,OnBeforeOrderSending,OnManagerBeforeOrderRender
 * @internal    @properties &title=Title;text; &shop_id=Uniteller Point ID;text; &shop_password=Password;text; &debug=Debug;list;No==0||Yes==1;1 
 * @internal    @modx_category Commerce
 * @internal    @installset base
 */

//<?php
/**
 * Payment YandexSplit
 *
 * YandexSplit payments processing
 *
 * @category    plugin
 * @version     1.0.0
 * @author      Pathologic
 * @internal    @events OnRegisterPayments,OnBeforeOrderSending,OnManagerBeforeOrderRender
 * @internal    @properties &title=Title;text; &client_id=Client ID;text; &secret_key=Secret Key;text; &debug=Debug;list;No==0||Yes==1;1 &test=Test mode;list;No==0||Yes==1;1 
 * @internal    @modx_category Commerce
 * @internal    @installset base
 */

$isSelectedPayment = !empty($order['fields']['payment_method']) && $order['fields']['payment_method'] == 'yandexsplit';
$commerce = ci()->commerce;
$lang = $commerce->getUserLanguage('yandexsplit');

switch ($modx->event->name) {
    case 'OnRegisterPayments': {
        $class = new \Commerce\Payments\YandexSplitPayment($modx, $params);
        if (empty($params['title'])) {
            $params['title'] = $lang['yandexsplit.caption'];
        }

        $commerce->registerPayment('yandexsplit', $params['title'], $class);
        break;
    }

    case 'OnBeforeOrderSending': {
        if ($isSelectedPayment) {
            $FL->setPlaceholder('extra', $FL->getPlaceholder('extra', '') . $commerce->loadProcessor()->populateOrderPaymentLink());
        }

        break;
    }

    case 'OnManagerBeforeOrderRender': {
        if (isset($params['groups']['payment_delivery']) && $isSelectedPayment) {
            $params['groups']['payment_delivery']['fields']['payment_link'] = [
                'title'   => $lang['yandexsplit.link_caption'],
                'content' => function($data) use ($commerce) {
                    return $commerce->loadProcessor()->populateOrderPaymentLink('@CODE:<a href="[+link+]" target="_blank">[+link+]</a>');
                },
                'sort' => 50,
            ];
        }

        break;
    }
}
