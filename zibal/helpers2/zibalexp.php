<?php
/**
 * zibal payment plugin.
 *
 * @author Jeremy Magne
 * @author Valérie Isaksen
 *
 * @version $Id: zibal.php 7217 2013-09-18 13:42:54Z alatak $
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
defined('_JEXEC') or die('Restricted access');


class zibalHelperzibalExp extends zibalHelperzibal
{
    public $api_login_id = '';
    public $api_signature = '';
    public $api_password = '';

    public function __construct($method, $zibalPlugin)
    {
        parent::__construct($method, $zibalPlugin);
        //Set the credentials
        if ($this->_method->sandbox) {
            $this->api_login_id = $this->_method->sandbox_api_login_id;
            if ($this->_method->authentication == 'signature') {
                $this->api_signature = $this->_method->sandbox_api_signature;
                $this->api_certificate = '';
            } else {
                $this->api_signature = '';
                $this->api_certificate = $this->_method->sandbox_api_certificate;
            }
            $this->api_password = $this->_method->sandbox_api_password;
            $this->merchant_email = $this->_method->sandbox_merchant_email;
        } else {
            $this->api_login_id = $this->_method->api_login_id;
            $this->api_signature = $this->_method->api_signature;
            $this->api_certificate = $this->_method->api_certificate;
            $this->api_password = $this->_method->api_password;
            $this->m1 = $this->_method->m1;
            $this->m2 = $this->_method->m2;
            $this->m3 = $this->_method->m3;
        }
        // if ((!$this->ExpCredentialsValid() OR !$this->isAacceleratedOnboardingValid())) {
            // $text = vmText::sprintf('VMPAYMENT_zibal_CREDENTIALS_NOT_SET', $this->_method->payment_name, $this->_method->virtuemart_paymentmethod_id);
            // vmError($text, $text);
        // }
        // if (empty ($this->_method->expected_maxamount)) {
            // $text = vmText::sprintf('VMPAYMENT_zibal_PARAMETER_REQUIRED', vmText::_('VMPAYMENT_zibal_EXPECTEDMAXAMOUNT'), $this->_method->payment_name, $this->_method->virtuemart_paymentmethod_id);
            // vmError($text, $text);
        // }
    }

    public function expCredentialsValid()
    {
        return $this->api_login_id && $this->api_password && ($this->api_signature || $this->api_certificate);
    }

    /**
     *      * Check if it is  Accelerated Boarding  possible for Express Checkout.
     *
     * @return bool
     */
    public function isAacceleratedOnboarding()
    {
        return $this->_method->accelerated_onboarding;
    }

    /**
     *      * Check if it is  Accelerated Boarding  possible for Express Checkout.
     *
     * @return bool
     */
    public function isAacceleratedOnboardingValid()
    {
        if ($this->_method->accelerated_onboarding and empty($this->merchant_email)) {
            return false;
        } else {
            return true;
        }
    }

    public function initPostVariables($zibalMethod)
    {
        $post_variables = [];
        $post_variables['METHOD'] = $zibalMethod;
        $post_variables['version'] = '104.0';
        // 104.0 required by zibal
        //https://developer.zibal.com/webapps/developer/docs/classic/release-notes/
        $post_variables['USER'] = $this->api_login_id;
        $post_variables['PWD'] = $this->api_password;
        $post_variables['BUTTONSOURCE'] = self::BNCODE;
        if ($this->api_signature) {
            $post_variables['SIGNATURE'] = $this->api_signature;
        }

        $post_variables['CURRENCYCODE'] = $this->currency_code_3;

        if (is_array($this->order) && is_object($this->order['details']['BT'])) {
            $post_variables['INVNUM'] = $this->order['details']['BT']->order_number;
        } else {
            if (is_object($this->order)) {
                $post_variables['INVNUM'] = $this->order->order_number;
            }
        }
        //$post_variables['IPADDRESS'] = ($_SERVER['REMOTE_ADDR'] == '::1') ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
        $IP = $this->getRemoteIPAddress();
        $post_variables['IPADDRESS'] = ($IP == '::1') ? '127.0.0.1' : $IP;

        return $post_variables;
    }

    public function addAcceleratedOnboarding(&$post_variables)
    {
        if ($this->_method->accelerated_onboarding) {
            $post_variables['SUBJECT'] = $this->merchant_email;
        }
    }

    public function addBillTo(&$post_variables)
    {
        $addressBT = $this->order['details']['BT'];

        //Bill To
        $post_variables['FIRSTNAME'] = isset($addressBT->first_name) ? $this->truncate($addressBT->first_name, 50) : '';
        $post_variables['LASTNAME'] = isset($addressBT->last_name) ? $this->truncate($addressBT->last_name, 50) : '';
        $post_variables['STREET'] = isset($addressBT->address_1) ? $this->truncate($addressBT->address_1, 60) : '';
        $post_variables['CITY'] = isset($addressBT->city) ? $this->truncate($addressBT->city, 40) : '';
        $post_variables['ZIP'] = isset($addressBT->zip) ? $this->truncate($addressBT->zip, 40) : '';
        $post_variables['STATE'] = isset($addressBT->virtuemart_state_id) ? ShopFunctions::getStateByID($addressBT->virtuemart_state_id, 'state_2_code') : '';
        $post_variables['COUNTRYCODE'] = ShopFunctions::getCountryByID($addressBT->virtuemart_country_id, 'country_2_code');
    }

    public function addShipTo(&$post_variables)
    {
        $addressST = ((isset($this->order['details']['ST'])) ? $this->order['details']['ST'] : $this->order['details']['BT']);

        //Ship To
        $shiptoname = $this->getShipToName((isset($addressST->first_name) ? $addressST->first_name : ''), (isset($addressST->last_name) ? $addressST->last_name : ''), 50);
        $post_variables['SHIPTONAME'] = $shiptoname;
        $post_variables['SHIPTOSTREET'] = isset($addressST->address_1) ? $this->truncate($addressST->address_1, 60) : '';
        $post_variables['SHIPTOCITY'] = isset($addressST->city) ? $this->truncate($addressST->city, 40) : '';
        $post_variables['SHIPTOZIP'] = isset($addressST->zip) ? $this->truncate($addressST->zip, 40) : '';
        $post_variables['SHIPTOSTATE'] = isset($addressST->virtuemart_state_id) ? ShopFunctions::getStateByID($addressST->virtuemart_state_id, 'state_2_code') : '';
        $post_variables['SHIPTOCOUNTRYCODE'] = ShopFunctions::getCountryByID($addressST->virtuemart_country_id, 'country_2_code');
    }

    public function getShipToName($first_name, $last_name, $max_length)
    {
        if (strlen($first_name.' '.$last_name) > $max_length) {
            $first_name = $this->truncate($first_name, $max_length - strlen($last_name));
        }
        // important that we get the last name correctly
        $shipToName = $this->truncate($first_name.' '.$last_name, $max_length);

        return $shipToName;
    }

    /**
     * https://developer.zibal.com/webapps/developer/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/.
     *
     * @param $post_variables
     */
    public function addPrices(&$post_variables)
    {
        $paymentCurrency = CurrencyDisplay::getInstance($this->_method->payment_currency);
        $i = 0;
        $taxAmount = 0;
        $total = 0;
        // Product prices
        if ($this->cart->products) {
            foreach ($this->cart->products as $key => $product) {
                $post_variables['L_PAYMENTREQUEST_0_NAME'.$i] = $this->getItemName($product->product_name);
                if ($product->product_sku) {
                    $post_variables['L_PAYMENTREQUEST_0_NUMBER'.$i] = $product->product_sku;
                }
                $post_variables['L_PAYMENTREQUEST_0_AMT'.$i] = $this->getProductAmount($this->cart->cartPrices[$key]);
                $post_variables['L_PAYMENTREQUEST_0_QTY'.$i] = $product->quantity;
                $total += $post_variables['L_PAYMENTREQUEST_0_AMT'.$i] * $post_variables['L_PAYMENTREQUEST_0_QTY'.$i];
                $i++;
            }
        }

        // Handling Coupon (handling must be positive value, add then coupon as a product with negative value
        if (!empty($this->cart->cartPrices['salesPriceCoupon'])) {
            $post_variables['L_PAYMENTREQUEST_0_NAME'.$i] = vmText::_('COM_VIRTUEMART_COUPON_DISCOUNT').': '.$this->cart->couponCode;
            $post_variables['L_PAYMENTREQUEST_0_AMT'.$i] = vmPSPlugin::getAmountValueInCurrency($this->cart->cartPrices['salesPriceCoupon'], $this->_method->payment_currency);
            $post_variables['L_PAYMENTREQUEST_0_QTY'.$i] = 1;
            $total += $post_variables['L_PAYMENTREQUEST_0_AMT'.$i] * $post_variables['L_PAYMENTREQUEST_0_QTY'.$i];
        }

        $post_variables['PAYMENTREQUEST_0_ITEMAMT'] = $total;
        $salesPriceShipment = vmPSPlugin::getAmountValueInCurrency($this->cart->cartPrices['salesPriceShipment'], $this->_method->payment_currency);
        if ($salesPriceShipment >= 0) {
            $post_variables['PAYMENTREQUEST_0_SHIPPINGAMT'] = $salesPriceShipment;
        } else {
            $post_variables['PAYMENTREQUEST_0_SHIPDISCAMT'] = $salesPriceShipment;
        }
        $total += $salesPriceShipment;
        $handling = $this->getHandlingAmount();

        $post_variables['PAYMENTREQUEST_0_HANDLINGAMT'] = $handling;
        $total += $handling;

        $post_variables['PAYMENTREQUEST_0_AMT'] = $total;
        $post_variables['PAYMENTREQUEST_0_CURRENCYCODE'] = $this->currency_code_3;

        $pricesCurrency = CurrencyDisplay::getInstance($this->cart->pricesCurrency);
    }

    public function addToken(&$post_variables)
    {
        $post_variables['TOKEN'] = $this->customerData->getVar('token');
        $post_variables['PAYERID'] = $this->customerData->getVar('payer_id');
    }

    /*
     * languages supported according to this https://cms.zibal.com/uk/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_ECCustomizing
     */
    public function getLocaleCode()
    {
        $jlang = JFactory::getLanguage();
        $tag = $jlang->getTag();
        $languageSpecific = [
            'da_DK', //', // – Danish (for Denmark only)
            'he_IL', //', // – Hebrew (all)
            'id_ID', //– Indonesian (for Indonesia only)
            'ja_JP', //', // – Japanese (for Japan only)
            'no_NO', //– Norwegian (for Norway only)
            'pt_BR', //', // – Brazilian Portuguese (for Portugal and Brazil only)
            'ru_RU', //', // – Russian (for Lithuania, Latvia, and Ukraine only)
            'sv_SE', //', // – Swedish (for Sweden only)
            'th_TH', //', // – Thai (for Thailand only)
            'tr_TR', //- //', // – Turkish (for Turkey only))
            'zh_CN', //– Simplified Chinese (for China only)
            'zh_HK', //– Traditional Chinese (for Hong Kong only)
            'zh_TW', // – Traditional Chinese (for Taiwan only)
        ];
        if (in_array($tag, $languageSpecific)) {
            return $tag;
        }

        $zibalLanguages = [
            'AU',
            'AT', // Austria
            'BE', //',  Belgium
            'BR', //  Brazil
            'CA', // – Canada
            'CH', //  Switzerland
            'CN', // – China
            'DE', // – Germany
            'ES', // – Spain
            'GB', // – United Kingdom
            'FR', // – France
            'IT', // – Italy
            'NL', // – Netherlands
            'PL', // – Poland
            'PT', // – Portugal
            'RU', // – Russia
            'US', // – United States
        ];
        $explode = explode('-', $tag);
        if (isset($explode[1])) {
            $country = $explode[1];
            if (in_array($country, $zibalLanguages)) {
                return $country;
            }
        }

        return 'GB';
    }

    public function getToken()
    {
        $post_variables = $this->initPostVariables('SetExpressCheckout');
        $this->addAcceleratedOnboarding($post_variables);

        $this->setTimeOut(self::TIMEOUT_SETEXPRESSCHECKOUT);
        $post_variables['PAYMENTREQUEST_0_CURRENCYCODE'] = $this->currency_code_3;
        if ($this->_method->virtuemart_paymentmethod_id == 0) {
            $msg = 'Programming error,zibal expresscheckout: virtuemart_paymentmethod_id is 0';
            vmError($msg, $msg);

            return;
        }
        // THIS IS A DIFFERENT URL FROM VM2
        $post_variables['RETURNURL'] = JURI::root().'index.php?option=com_virtuemart&view=plugin&type=vmpayment&name='.$this->_method->payment_element.'&action=SetExpressCheckout&SetExpressCheckout=done&pm='.$this->_method->virtuemart_paymentmethod_id;

        $post_variables['CANCELURL'] = JURI::root().'index.php?option=com_virtuemart&view=plugin&type=vmpayment&name='.$this->_method->payment_element.'&action=SetExpressCheckout&SetExpressCheckout=cancel&pm='.$this->_method->virtuemart_paymentmethod_id.'&Itemid='.vRequest::getInt('Itemid').'&lang='.vRequest::getCmd('lang', '');
        //$post_variables['CANCELURL'] = substr(JURI::root(false,''),0,-1). JROUTE::_('index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&expresscheckout=cancel');
        $post_variables['ADDROVERRIDE'] = $this->_method->address_override;
        $post_variables['NOSHIPPING'] = $this->_method->no_shipping;
        $post_variables['MAXAMT'] = $this->_method->expected_maxamount;

        $post_variables['LOGOIMG'] = $this->getLogoImage();
        //$this->debugLog($post_variables['LOGOIMG'], 'logoImg:', 'debug');

        $post_variables['LOCALECODE'] = $this->getLocaleCode();

        if ($this->_method->headerimg) {
            //$post_variables['HDRIMG'] = JURI::base()  . 'images/stories/virtuemart/payment/' . $this->_method->headerimg;
        }
        if ($this->_method->bordercolor) {
            $post_variables['CARTBORDERCOLOR'] = str_replace('#', '', strtoupper($this->_method->bordercolor));
            //$post_variables['PAYFLOWCOLOR'] = 'ff0033'; //str_replace('#','',strtoupper($this->_method->bordercolor));
        }

        if ($this->_method->payment_type == '_xclick-subscriptions') {
            $post_variables['L_BILLINGTYPE0'] = 'RecurringPayments';
            $post_variables['L_BILLINGAGREEMENTDESCRIPTION0'] = $this->getRecurringProfileDesc();
        } else {
            $post_variables['PAYMENTREQUEST_0_PAYMENTACTION'] = $this->getPaymentAction();
            // done in addPrices
            // Total of order, including shipping, handling, tax, and any other billing adjustments such as a credit due.
            if ($this->total == 0) {
                $msg = 'Programming error,zibal expresscheckout: total sent is 0';
                vmError($msg, $msg);

                return;
            }
            $post_variables['PAYMENTREQUEST_0_AMT'] = $this->total;
            $post_variables['PAYMENTREQUEST_0_CURRENCYCODE'] = $this->currency_code_3;
        }
        // It is almost impossible to have the same amount as the one calultaed by zibal
        if (isset($this->_method->add_prices_api) and $this->_method->add_prices_api) {
            $this->addPrices($post_variables);
        }

        $this->sendRequest($post_variables);
        $valid = $this->handleResponse();
        if ($valid) {
            $this->customerData->setVar('token', $this->response['TOKEN']);
            $this->customerData->save();
            $this->redirectTozibal();
        } else {
            // already done in handleResponse()
            // $this->customerData->clear();
            return false;
        }

        return true;
    }

    public function getExpressCheckoutDetails()
    {
        $post_variables = $this->initPostVariables('GetExpressCheckoutDetails');
        $this->addAcceleratedOnboarding($post_variables);

        $this->setTimeOut(self::TIMEOUT_GETEXPRESSCHECKOUTDETAILS);

        $this->addToken($post_variables);

        $this->sendRequest($post_variables);
        if ($this->handleResponse()) {
            $this->customerData->setVar('payer_id', $this->response['PAYERID']);
            $this->customerData->setVar('first_name', $this->response['FIRSTNAME']);
            $this->customerData->setVar('last_name', $this->response['LASTNAME']);
            $this->customerData->setVar('payer_email', $this->response['EMAIL']);
            $this->customerData->setVar('zibal_response', $this->response);
            $this->customerData->save();
            $this->storeAddresses();

            return true;
        } else {
            return false;
        }
    }

    public function ManageLogin()
    {
    }

    public function ManageCheckout()
    {
        switch ($this->_method->payment_type) {
            case '_xclick':
                return $this->DoPayment();
            case '_xclick-subscriptions':
                return $this->CreateRecurringPaymentsProfile();
            case '_xclick-payment-plan':
                return $this->CreatePaymentPlanProfile();
        }
    }

    public function ManageCancelOrder($payment)
    {
        $this->RefundTransaction($payment);
        /*
        switch ($this->_method->payment_type) {
            case '_xclick':
                return $this->RefundTransaction($payment);
            case '_xclick-subscriptions':
            case '_xclick-payment-plan':
                return $this->ManageRecurringPaymentsProfileStatus($payment);
        }
        */
    }

    public function DoPayment()
    {
        static $redirect = 0;
        $post_variables = $this->initPostVariables('DoExpressCheckoutPayment');
        $this->addAcceleratedOnboarding($post_variables);
        $this->addBillTo($post_variables);
        $this->addShipTo($post_variables);
        if (isset($this->_method->add_prices_api) and $this->_method->add_prices_api) {
            $this->addPrices($post_variables);
        }

        $this->addToken($post_variables);

        $post_variables['PAYMENTREQUEST_0_CURRENCYCODE'] = $this->currency_code_3;
        $post_variables['PAYMENTREQUEST_0_PAYMENTACTION'] = $this->getPaymentAction();
        $post_variables['PAYMENTREQUEST_0_AMT'] = $this->total;

        $this->sendRequest($post_variables);
        // https://developer.zibal.com/webapps/developer/docs/classic/express-checkout/ht_ec_fundingfailure10486/
        $responseValid = $this->handleResponse();
        if (!$responseValid) {
            if ($this->response['L_ERRORCODE0'] == self::FRAUD_FAILURE_ERROR_CODE and $this->_method->payment_action == 'Sale' and $redirect <= 2) {
                $redirect++;
                // redirect buyer to zibal
                $this->redirectTozibal($post_variables['TOKEN']);
            }

            return false;
        }

        return true;
    }

    public function CreateRecurringPaymentsProfile()
    {
        //https://developer.zibal.com/webapps/developer/docs/classic/direct-payment/ht_dp-recurringPaymentProfile-curl-etc/
        //https://developer.zibal.com/webapps/developer/docs/classic/api/merchant/CreateRecurringPaymentsProfile_API_Operation_NVP/

        $post_variables = $this->initPostVariables('CreateRecurringPaymentsProfile');
        $this->addBillTo($post_variables);
        $this->addShipTo($post_variables);
        $this->addToken($post_variables);

        //$post_variables['SUBSCRIBERNAME']	= isset($addressBT->first_name) ? $this->truncate($addressBT->first_name, 50) : '';
        $post_variables['PROFILEREFERENCE'] = $this->order['details']['BT']->order_number;
        $post_variables['DESC'] = $this->getRecurringProfileDesc();

        $startDate = JFactory::getDate();
        $post_variables['PROFILESTARTDATE'] = $startDate->toISO8601();
        $post_variables['AUTOBILLOUTAMT'] = 'AddToNextBilling';

        $post_variables['BILLINGFREQUENCY'] = $this->getDurationValue($this->_method->subscription_duration);
        $post_variables['BILLINGPERIOD'] = $this->getDurationUnit($this->_method->subscription_duration);
        $post_variables['TOTALBILLINGCYCLES'] = $this->_method->subscription_term;

        if ($this->cart->cartPrices['salesPricePayment']) {
            $post_variables['INITAMT'] = $this->cart->cartPrices['salesPricePayment'];
            $post_variables['FAILEDINITAMTACTION'] = 'CancelOnFailure';
            $post_variables['AMT'] = $this->total - $this->cart->cartPrices['salesPricePayment'];
        } else {
            $post_variables['AMT'] = $this->total;
        }

        if ($this->_method->subcription_trials) {
            $post_variables['TRIALBILLINGFREQUENCY'] = $this->getDurationValue($this->_method->trial1_duration);
            $post_variables['TRIALBILLINGPERIOD'] = $this->getDurationUnit($this->_method->trial1_duration);
            $post_variables['TRIALTOTALBILLINGCYCLES'] = $this->_method->subcription_trials;
            $post_variables['TRIALAMT'] = ($this->_method->trial1_price) ? $this->_method->trial1_price : 0;
        }

        $this->sendRequest($post_variables);

        return $this->handleResponse();
    }

    public function CreatePaymentPlanProfile()
    {
        //Payment plans are not implemented in the API.
        //A workaround is to create a subscription profile and divide the total amount by the term.

        $post_variables = $this->initPostVariables('CreateRecurringPaymentsProfile');
        $this->addBillTo($post_variables);
        $this->addShipTo($post_variables);
        $this->addToken($post_variables);

        //$post_variables['SUBSCRIBERNAME']	= isset($addressBT->first_name) ? $this->truncate($addressBT->first_name, 50) : '';
        $post_variables['PROFILEREFERENCE'] = $this->order['details']['BT']->order_number;
        $post_variables['DESC'] = $this->order['details']['BT']->order_number.': '.$this->getPaymentPlanDesc();

        if ($this->cart->cartPrices['salesPricePayment'] && $this->cart->cartPrices['salesPricePayment'] > 0) {
            $initAmount = $this->cart->cartPrices['salesPricePayment'];
        } else {
            $initAmount = 0;
        }
        $occurence_amount = round(($this->total - $initAmount) / $this->_method->payment_plan_term, 2);
        if ($this->_method->payment_plan_defer == 2) {
            $initAmount += $occurence_amount;
            $occurences_count = $this->_method->payment_plan_term - 1;
        } else {
            $occurences_count = $this->_method->payment_plan_term;
        }

        if ($this->_method->payment_plan_defer && $this->_method->payment_plan_defer_strtotime) {
            $startDate = JFactory::getDate($this->_method->payment_plan_defer_strtotime);
        } else {
            $startDate = JFactory::getDate();
        }
        $post_variables['PROFILESTARTDATE'] = $startDate->toISO8601();
        $post_variables['AUTOBILLOUTAMT'] = 'AddToNextBilling';

        $post_variables['BILLINGFREQUENCY'] = $this->getDurationValue($this->_method->payment_plan_duration);
        $post_variables['BILLINGPERIOD'] = $this->getDurationUnit($this->_method->payment_plan_duration);
        $post_variables['TOTALBILLINGCYCLES'] = $occurences_count;

        if ($this->cart->cartPrices['salesPricePayment'] && $this->cart->cartPrices['salesPricePayment'] > 0) {
            $post_variables['INITAMT'] = $initAmount;
            $post_variables['FAILEDINITAMTACTION'] = 'CancelOnFailure';
        }
        $post_variables['AMT'] = $occurence_amount;

        $this->sendRequest($post_variables);

        return $this->handleResponse();
    }

    public function GetRecurringPaymentsProfileDetails($profileId)
    {
        $post_variables = $this->initPostVariables('GetRecurringPaymentsProfileDetails');
        $post_variables['PROFILEID'] = $profileId;

        $this->sendRequest($post_variables);

        return $this->handleResponse();
    }

    public function ManageRecurringPaymentsProfileStatus($payment)
    {
        $zibal_data = json_decode($payment->zibal_fullresponse);
        $post_variables = $this->initPostVariables('ManageRecurringPaymentsProfileStatus');
        $post_variables['PROFILEID'] = $zibal_data->PROFILEID;
        $post_variables['ACTION'] = 'Cancel';
        $post_variables['TOKEN'] = $zibal_data->TOKEN;
        $post_variables['PAYERID'] = $zibal_data->payer_id;

        $this->sendRequest($post_variables);
        $this->handleResponse();

        return $this->GetRecurringPaymentsProfileDetails($zibal_data->PROFILEID);
    }

    public function DoCapture($payment)
    {
        $zibal_data = json_decode($payment->zibal_fullresponse);
        //Only capture payment if it still pending
        if (strcasecmp($zibal_data->PAYMENTINFO_0_PAYMENTSTATUS, 'Pending') != 0 && strcasecmp($zibal_data->PAYMENTINFO_0_PENDINGREASON, 'Authorization') != 0) {
            return false;
        }

        $post_variables = $this->initPostVariables('DoCapture');

        //Do we need to reauthorize ?

        $reauth = $this->doReauthorize($zibal_data->PAYMENTINFO_0_TRANSACTIONID, $zibal_data);
        if ($reauth === false) {
            $post_variables['AUTHORIZATIONID'] = $zibal_data->PAYMENTINFO_0_TRANSACTIONID;
        } else {
            $post_variables['AUTHORIZATIONID'] = $reauth;
        }

        $post_variables['TOKEN'] = $zibal_data->TOKEN;
        $post_variables['PAYERID'] = $zibal_data->payer_id;
        $post_variables['PAYMENTACTION'] = 'DoCapture';
        $post_variables['AMT'] = $this->total;
        $post_variables['COMPLETETYPE'] = 'Complete';

        $this->sendRequest($post_variables);
        $success = $this->handleResponse();
        if (!$success) {
            $this->doVoid($payment);
        }

        return $success;
    }

    public function doReauthorize($AuthorizationID, $zibal_data)
    {
        // TODO
        return false;
        $post_variables = $this->initPostVariables('DoReauthorization');
        $post_variables['TOKEN'] = $zibal_data->TOKEN;
        $post_variables['PAYERID'] = $zibal_data->payer_id;
        $post_variables['AuthorizationID'] = $AuthorizationID;
        $post_variables['PAYMENTACTION'] = 'DoReauthorization';
        $post_variables['AMT'] = $this->total;
        $post_variables['CURRENCYCODE'] = $zibal_data->PAYMENTINFO_0_CURRENCYCODE;

        $this->sendRequest($post_variables);
        if ($this->handleResponse()) {
            return $this->response['AUTHORIZATIONID'];
        } else {
            $error = '';
            for ($i = 0; isset($this->response['L_ERRORCODE'.$i]); $i++) {
                $error .= $this->response['L_ERRORCODE'.$i];
                $message = isset($this->response['L_LONGMESSAGE'.$i]) ? $this->response['L_LONGMESSAGE'.$i] : $this->response['L_SHORTMESSAGE'.$i];
                $error .= ':'.$message.'<br />';
            }

            VmError($error);

            return false;
        }
    }

    public function RefundTransaction($payment)
    {
        $zibal_data = json_decode($payment->zibal_fullresponse);
        if ($payment->zibal_response_payment_status == 'Completed') {
            $post_variables = $this->initPostVariables('RefundTransaction');
            $post_variables['REFUNDTYPE'] = 'Full';
        } else {
            if ($payment->zibal_response_payment_status == 'Pending' && $payment->zibal_response_pending_reason == 'authorization') {
                $post_variables = $this->initPostVariables('DoVoid');
            } else {
                return false;
            }
        }

        $post_variables['AuthorizationID'] = $payment->zibal_response_txn_id;
        $post_variables['TRANSACTIONID'] = $payment->zibal_response_txn_id;
        $post_variables['TOKEN'] = $zibal_data->TOKEN;
        $post_variables['PAYERID'] = $zibal_data->payer_id;

        $this->sendRequest($post_variables);

        return $this->handleResponse();
    }

    public function doVoid($payment)
    {
        $zibal_data = json_decode($payment->zibal_fullresponse);
        $post_variables = $this->initPostVariables('DoVoid');
        $post_variables['AuthorizationID'] = $payment->zibal_response_txn_id;
        $post_variables['TRANSACTIONID'] = $payment->zibal_response_txn_id;
        $post_variables['TOKEN'] = $zibal_data->TOKEN;
        $post_variables['PAYERID'] = $zibal_data->payer_id;

        $this->sendRequest($post_variables);

        return $this->handleResponse();
    }

    public function isFraudDetected()
    {
        if ($this->response['ACK'] == 'SuccessWithWarning' && $this->response['L_ERRORCODE0'] == self::FMF_PENDED_ERROR_CODE && $this->response['PAYMENTSTATUS'] == 'Pending'
        ) {
            $this->debugLog($this->response, 'Fraud Detected', 'error');

            return true;
        } else {
            return false;
        }
    }

    public function getNewOrderStatus()
    {
        if ($this->isFraudDetected()) {
            $new_status = $this->_method->status_fraud;
        } elseif ($this->_method->payment_action == 'Authorization' || $this->_method->payment_type == '_xclick-payment-plan' || $this->response['ACK'] == 'SuccessWithWarning' || $this->response['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Pending') {
            $new_status = $this->_method->status_pending;
        } else {
            $new_status = $this->_method->status_success;
        }

        return $new_status;
    }

    /**
     * How To Recover from Funding Failure Error Code 10486 in DoExpressCheckoutPayment
     * https://developer.zibal.com/docs/classic/express-checkout/ht_ec_fundingfailure10486/.
     *
     * @return bool
     */
    public function handleResponse()
    {
        if ($this->response) {
            if ($this->response['ACK'] == 'Failure' || $this->response['ACK'] == 'FailureWithWarning') {
                if ($this->response['L_ERRORCODE0'] != self::FRAUD_FAILURE_ERROR_CODE) {
                    $this->customerData->clear();
                }
                $error = '';
                $public_error = '';

                for ($i = 0; isset($this->response['L_ERRORCODE'.$i]); $i++) {
                    $error .= $this->response['L_ERRORCODE'.$i];
                    $message = isset($this->response['L_LONGMESSAGE'.$i]) ? $this->response['L_LONGMESSAGE'.$i] : $this->response['L_SHORTMESSAGE'.$i];
                    $error .= ': '.$message.'<br />';
                }
                if ($this->_method->debug) {
                    $public_error = $error;
                }
                $this->debugLog($this->response, 'handleResponse:', 'debug');
                VmError($error, $public_error);

                return false;
            } elseif ($this->response['ACK'] == 'Success' || $this->response['ACK'] == 'SuccessWithWarning' || $this->response['TRANSACTIONID'] != null || $this->response['PAYMENTINFO_0_TRANSACTIONID'] != null) {
                return true;
            } else {
                // Unexpected ACK type. Log response and inform the buyer that the
                // transaction must be manually investigated.
                $error = '';
                $public_error = '';
                $error = 'Unexpected ACK type:'.$this->response['ACK'];
                $this->debugLog($this->response, 'Unexpected ACK type:', 'debug');
                if ($this->_method->debug) {
                    $public_error = $error;
                }
                VmError($error, $public_error);

                return false;
            }
        }
    }

    public function storeAddresses()
    {
        $this->cart = VirtueMartCart::getCart();
        $addressST = $addressBT = [];
        if ($this->response['SHIPTONAME'] == $this->response['FIRSTNAME'].' '.$this->response['LASTNAME']) {
            $firstName = $this->response['FIRSTNAME'];
            $lastName = $this->response['LASTNAME'];
        } else {
            $shipToName = explode(' ', $this->response['SHIPTONAME']);
            $firstName = $shipToName[0];
            $lastName = '';
            if (count($shipToName) > 1) {
                $lastName = str_replace($firstName.' ', '', $this->response['SHIPTONAME']);
            }
        }
        if ($this->cart->BT == 0 or empty($this->cart->BT)) {
            $addressBT['email'] = $this->response['EMAIL'];
            $addressBT['first_name'] = $firstName;
            $addressBT['last_name'] = $lastName;
            $addressBT['address_1'] = $this->response['SHIPTOSTREET'];
            $addressBT['city'] = $this->response['SHIPTOCITY'];
            $addressBT['zip'] = $this->response['SHIPTOZIP'];
            $addressBT['virtuemart_state_id'] = ShopFunctions::getStateIDByName($this->response['SHIPTOSTATE']);
            $addressBT['virtuemart_country_id'] = ShopFunctions::getCountryIDByName($this->response['SHIPTOCOUNTRYCODE']);
            $this->cart->saveAddressInCart($addressBT, 'BT', true);
        }

        $addressST['shipto_address_type_name'] = 'zibal Account';
        $addressST['shipto_first_name'] = $firstName;
        $addressST['shipto_last_name'] = $lastName;
        $addressST['shipto_address_1'] = $this->response['SHIPTOSTREET'];
        $addressST['shipto_city'] = $this->response['SHIPTOCITY'];
        $addressST['shipto_zip'] = $this->response['SHIPTOZIP'];
        $addressST['shipto_virtuemart_state_id'] = ShopFunctions::getStateIDByName($this->response['SHIPTOSTATE']);
        $addressST['shipto_virtuemart_country_id'] = ShopFunctions::getCountryIDByName($this->response['SHIPTOCOUNTRYCODE']);
        $this->cart->STsameAsBT = 0;
        $this->cart->setCartIntoSession();
        $this->cart->saveAddressInCart($addressST, 'ST', true);
    }

    public function storeNoteToSeller()
    {
        if (array_key_exists('PAYMENTREQUEST_0_NOTETEXT', $this->response)) {
            $this->cart = VirtueMartCart::getCart();
            $this->cart->customer_comment = $this->response['PAYMENTREQUEST_0_NOTETEXT'];
            $this->cart->setCartIntoSession();
        }
    }

    public function storePayerId()
    {
        if (array_key_exists('PAYERID', $this->response)) {
            $this->customerData->setVar('payer_id', $this->response['PAYERID']);
            $this->customerData->save();
        }
    }

    public function storePayerStatus()
    {
        if (array_key_exists('PAYERSTATUS', $this->response)) {
            $this->customerData->setVar('payerstatus', $this->response['PAYERSTATUS']);
            $this->customerData->save();
        }
    }

    public function redirectTozibal($token = '')
    {
        $useraction = '';
        if ($this->response['method'] == 'DoExpressCheckoutPayment') {
            $useraction = '&useraction=commit';
        }
        if (empty($token)) {
            $token = $this->response['TOKEN'];
        }
        jimport('joomla.environment.browser');
        $browser = JBrowser::getInstance();
        if ($browser->isMobile()) {
            $url = $this->_getzibalUrl().'?cmd=_express-checkout-mobile&token='.$token.$useraction;
        } else {
            $url = $this->_getzibalUrl().'?cmd=_express-checkout&token='.$token.$useraction;
        }

        if ($this->_method->debug) {
            echo '<div style="background-color:red;color:white;padding:10px;">The method is in debug mode. <a href="'.$url.'">Click here to be redirected to zibal</a></div>';
            jexit();
        } else {
            //header('location: ' . $url);
            $app = JFactory::getApplication();
            $app->redirect($url);
        }
    }

    public function validate($enqueueMessage = true)
    {
        //if (!$this->customerData->getVar('token') || $this->cart->virtuemart_paymentmethod_id != $this->customerData->getVar('selected_method')) {
        if (!$this->customerData->getVar('token')) {
            $this->getToken();
            //Code stops here as the getToken method should redirect to zibal
        } elseif (!$this->customerData->getVar('payer_id')) {
            $this->customerData->clear();
            $this->getToken();
        } else {
            return parent::validate();
        }
    }

    public function setExpressCheckout($enqueueMessage = true)
    {
        //if (!$this->customerData->getVar('token') || $this->cart->virtuemart_paymentmethod_id != $this->customerData->getVar('selected_method')) {
        // Checks if there is already a token. If not create one.

        if (!$this->customerData->getVar('token')) {
            $this->getToken();
            //Code stops here as the getToken method should redirect to zibal
        } else {
            return parent::validate();
        }
        $success = $this->ManageCheckout(true);
        $response = $this->getResponse();
    }

    public function getResponse($withCustomerData = true)
    {
        $response = parent::getResponse();

        if (is_array($this->order) && is_object($this->order['details']['BT'])) {
            $response['invoice'] = $this->order['details']['BT']->order_number;
        } else {
            if (is_object($this->order)) {
                $response['invoice'] = $this->order->order_number;
            }
        }

        if ($withCustomerData) {
            $response['payer_id'] = $this->customerData->getVar('payer_id');
            $response['first_name'] = $this->customerData->getVar('first_name');
            $response['last_name'] = $this->customerData->getVar('last_name');
            $response['payer_email'] = $this->customerData->getVar('payer_email');
        }

        return $response;
    }

    public function getExtraPluginInfo()
    {
        $extraInfo = '';

        //Are we coming back from Express Checkout?
        $expressCheckout = vRequest::getVar('SetExpressCheckout', '');

        if (!$this->customerData->getVar('token')) {
            $this->getToken();
        } elseif ($expressCheckout == 'done') {
            $this->getExpressCheckoutDetails();
        }

        $extraInfo .= parent::getExtraPluginInfo();

        return $extraInfo;
    }

    protected function getDurationUnit($duration)
    {
        $parts = explode('-', $duration);
        switch ($parts[1]) {
            case 'D':
                return 'Day';
            case 'W':
                return 'Week';
            case 'M':
                return 'Month';
            case 'Y':
                return 'Year';
        }
    }

    /**
     * ￼Accelerated Onboarding only allowed for Sales Payment.
     *
     * @return string
     */
    public function GetPaymentAction()
    {
        if ($this->isAacceleratedOnboarding()) {
            return 'Sale';
        } else {
            return $this->_method->payment_action;
        }
    }

    /**
     * This page returns a 404 https://www.zibal.com/mx/cgi-bin/?cmd=xpt/Merchant/merchant/ExpressCheckoutButtonCode-outside.
     *
     * code form here
     * https://www.zibalobjects.com/IntegrationCenter/ic_express-buttons.html
     *
     * @return array
     */
    public function getExpressCheckoutButton()
    {
        $button = [];

        $lang = jFactory::getLanguage();
        $lang_iso = str_replace('-', '_', $lang->gettag());
        $available_buttons = ['en_US', 'en_GB', 'de_DE', 'es_ES', 'pl_PL', 'nl_NL', 'fr_FR', 'it_IT', 'zn_CN'];
        if (!in_array($lang_iso, $available_buttons)) {
            $lang_iso = 'en_US';
        }
        // SetExpressCheckout
        $button['link'] = JURI::root().'index.php?option=com_virtuemart&view=plugin&type=vmpayment&name='.$this->_method->payment_element.'&action=SetExpressCheckout&virtuemart_paymentmethod_id='.$this->_method->virtuemart_paymentmethod_id;
        $button['img'] = JURI::root().'plugins/vmpayment/'.$this->_method->payment_element.'/'.$this->_method->payment_element.'/assets/images/PP_Buttons_CheckOut_119x24_v3.png';

        return $button;
    }

    public function getExpressProduct()
    {
        $lang = jFactory::getLanguage();
        $lang_iso = str_replace('-', '_', $lang->gettag());
        $zibal_buttonurls = [
            'en_US' => 'https://www.zibal.com/en_US/i/logo/zibal_mark_60x38.gif',
            'en_GB' => 'https://www.zibal.com/en_GB/i/bnr/horizontal_solution_PP.gif',
            'de_DE' => 'https://www.zibal.com/de_DE/DE/i/logo/lockbox_150x47.gif',
            'es_ES' => 'https://www.zibalobjects.com/WEBSCR-600-20100105-1/en_US/FR/i/bnr/bnr_horizontal_solution_PP_327wx80h.gif',
            'pl_PL' => 'https://www.zibalobjects.com/WEBSCR-600-20100105-1/en_US/FR/i/bnr/bnr_horizontal_solution_PP_327wx80h.gif',
            'nl_NL' => 'https://www.zibalobjects.com/WEBSCR-600-20100105-1/en_US/FR/i/bnr/bnr_horizontal_solution_PP_327wx80h.gif',
            'fr_FR' => 'https://www.zibalobjects.com/WEBSCR-600-20100105-1/en_US/FR/i/bnr/bnr_horizontal_solution_PP_327wx80h.gif',
            'it_IT' => 'https://www.zibalobjects.com/WEBSCR-600-20100105-1/it_IT/IT/i/bnr/bnr_horizontal_solution_PP_178wx80h.gif',
            'zn_CN' => 'https://www.zibalobjects.com/WEBSCR-600-20100105-1/en_US/FR/i/bnr/bnr_horizontal_solution_PP_327wx80h.gif',
        ];
        $zibal_infolink = [
            'en_US' => 'https://www.zibal.com/us/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIszibal-outside',
            'en_GB' => 'https://www.zibal.com/uk/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIszibal-outside',
            'de_DE' => 'https://www.zibal.com/de/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIszibal-outside',
            'es_ES' => 'https://www.zibal.com/es/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIszibal-outside',
            'pl_PL' => 'https://www.zibal.com/pl/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIszibal-outside',
            'nl_NL' => 'https://www.zibal.com/nl/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIszibal-outside',
            'fr_FR' => 'https://www.zibal.com/fr/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIszibal-outside',
            'it_IT' => 'https://www.zibal.com/it/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIszibal-outside',
            'zn_CN' => 'https://www.zibal.com/cn/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIszibal-outside',
        ];
        if (!isset($zibal_buttonurls[$lang_iso])) {
            $lang_iso = 'en_US';
        }
        $zibalProduct['link'] = $zibal_infolink[$lang_iso];
        $zibalProduct['img'] = $zibal_buttonurls[$lang_iso];

        return $zibalProduct;
    }

    public function getOrderBEFields()
    {
        $showOrderFields = [
            'ACK'                        => 'PAYMENTINFO_0_ACK',
            'TXN_ID'                     => 'PAYMENTINFO_0_TRANSACTIONID',
            'CORRELATIONID'              => 'CORRELATIONID',
            'PAYER_ID'                   => 'payer_id',
            'MC_GROSS'                   => 'PAYMENTINFO_0_AMT',
            'MC_FEE'                     => 'PAYMENTINFO_0_FEEAMT',
            'TAXAMT'                     => 'PAYMENTINFO_0_TAXAMT',
            'MC_CURRENCY'                => 'PAYMENTINFO_0_CURRENCYCODE',
            'PAYMENT_STATUS'             => 'PAYMENTINFO_0_PAYMENTSTATUS',
            'PENDING_REASON'             => 'PAYMENTINFO_0_PENDINGREASON',
            'REASON_CODE'                => 'PAYMENTINFO_0_REASONCODE',
            'ERRORCODE'                  => 'PAYMENTINFO_0_ERRORCODE',
            'PROTECTION_ELIGIBILITY'     => 'PAYMENTINFO_0_PROTECTIONELIGIBILITY',
            'PROTECTION_ELIGIBILITYTYPE' => 'PAYMENTINFO_0_PROTECTIONELIGIBILITYTYPE',
        ];

        return $showOrderFields;
    }

    public function highlight($field)
    {
        return '<span style="color:red;font-weight:bold">'.$field.'</span>';
    }
}
