<?php

defined('_JEXEC') or die('Restricted access');

/*
 * @version $Id: zibal.php,v 1.4 2005/05/27 19:33:57 ei
 *
 * a special type of 'cash on delivey':
 * @author Max Milbers, Valérie Isaksen
 * @version $Id: zibal.php 5122 2011-12-18 22:24:49Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (c) 2004 - 2014 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists('vmPSPlugin')) {
    require JPATH_VM_PLUGINS.DS.'vmpsplugin.php';
}

class plgVmPaymentzibal extends vmPSPlugin
{
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        // 		vmdebug('Plugin stuff',$subject, $config);
        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     *
     * @author Valérie Isaksen
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment zibal Table');
    }

    /**
     * Fields to create the payment table.
     *
     * @return string SQL Fileds
     */
    public function getTableSQLFields()
    {
        $SQLfields = [
            'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'         => 'int(1) UNSIGNED',
            'order_number'                => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name'                => 'varchar(5000)',
            'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'            => 'char(3)',
            'email_currency'              => 'char(3)',
            'cost_per_transaction'        => 'decimal(10,2)',
            'cost_percent_total'          => 'decimal(10,2)',
            'tax_id'                      => 'smallint(1)',
        ];

        return $SQLfields;
    }

    /**
     * @author Valérie Isaksen
     */
    public function plgVmConfirmedOrder($cart, $order)
    {

        // echo "1"; exit;

        if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelOrders')) {
            require VMPATH_ADMIN.DS.'models'.DS.'orders.php';
        }
        if (!class_exists('VirtueMartModelCurrency')) {
            require VMPATH_ADMIN.DS.'models'.DS.'currency.php';
        }

        $params = $this->_currentMethod;

        $new_status = '';

        $usrBT = $order['details']['BT'];
        $address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);
        $this->getPaymentCurrency($method);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="'.$method->payment_currency.'" ';
        $db = &JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();

        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);

        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['vnpassargad_custom'] = $return_context;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $method->payment_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($dbValues);

        $onorder = rand(11111111111111, 999999999999);
        $amount = round($totalInPaymentCurrency); // مبلغ فاكتور
        $CallbackURL = ''.JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on='.$order['details']['BT']->order_number.'&onorder='.$onorder.'&pm='.$order['details']['BT']->virtuemart_paymentmethod_id.'&cur='.$params->currency;

        $ApiKey = $params->api;  //Required
        if ($params->currency == 'Toman') {
            $Amount = $amount*10;
        } else {
            $Amount = $amount;
        }
        $Order_ID = $dbValues['order_number'];  // Required

        $result = $this->postToZibal('request',array(
            'merchant'     => $ApiKey,
            'amount'         => $Amount,
            'orderId'    => $Order_ID,
            'callbackUrl'    => $CallbackURL,
        ));

        if(intval($result->result) == 100){
            header('Location: https://gateway.zibal.ir/start/'.$result->trackId.'/direct');
        } else {
            echo'ERR: '.$result->message;
        }
    }


    /**
     * connects to zibal's rest api
     * @param $path
     * @param $parameters
     * @return stdClass
     */
    public function postToZibal($path, $parameters)
    {
        $url = 'https://gateway.zibal.ir/v1/'.$path;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response  = curl_exec($ch);
        curl_close($ch);
        return json_decode($response);
    }

    public function plgVmOnPaymentResponseReceived(&$html)
    {

        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
        $order_number = JRequest::getVar('on', 0);

        $vendorId = 0;
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        if (!class_exists('VirtueMartCart')) {
            require JPATH_VM_SITE.DS.'helpers'.DS.'cart.php';
        }
        if (!class_exists('shopFunctionsF')) {
            require JPATH_VM_SITE.DS.'helpers'.DS.'shopfunctionsf.php';
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'orders.php';
        }
        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
        if ($virtuemart_order_id) {
            if (!class_exists('VirtueMartCart')) {
                $params = $this->_currentMethod;
            }

            $cart = VirtueMartCart::getCart();
            $ons = $_GET['on'];

            $authority = $_REQUEST['trackId'];

/////////////////////////////////////////////////////
        $db = JFactory::getDBO();
            $query = "select * from `#__virtuemart_orders` where `order_number` = '$ons'";
            $db->setQuery($query);
            $am = $db->loadObject();

            $ApiKey = $method->api;
            $amount = round($am->order_total);
            if ($_GET['cur'] == 'Toman') {
                $Amount = $amount*10;
            } else {
                $Amount = $amount;
            }
            $Trans_ID = $_GET['trackId'];
            $Order_ID = $_GET['orderId'];
            $success = $_GET['success'];

            if ($Trans_ID && $Order_ID && $success=='1') {

                    $result = $this->postToZibal('verify',array(
                    'merchant'     => $ApiKey,
                    'trackId'      => $Trans_ID,
                    ));

                if(intval($result->result) == 100 && $result->amount==$Amount){
                    echo '<div style="color:green; font-family:tahoma; direction:rtl; text-align:right">
			پرداخت با موفقیت انجام شد !
			<br /></div>';
                    echo "<br><br>";
                    echo '<br/><h3>شماره سفارش :'.$Order_ID.'</h3><br><h3>'.'شماره تراکنش:'.$Trans_ID.'</h3>';
                    //////
                    $dbcoupon = JFactory::getDBO();
                    $inscoupon = new stdClass();
                    $inscoupon->order_status = 'C';
                    $inscoupon->order_number = "$ons";
                    if ($dbcoupon->updateObject('#__virtuemart_orders', $inscoupon, 'order_number')) {
                        unset($dbcoupon);
                    } else {
                        echo $dbcoupon->stderr();
                    }
                    /////
                    $dbcccwpp = &JFactory::getDBO();
                    $dbcccowpp = "select * from `#__virtuemart_orders` where `order_number` = '$ons' AND `order_status` ='C'";
                    $dbcccwpp->setQuery($dbcccowpp);
                    $dbcccwpp->query();
                    $dbcccowpp = $dbcccwpp->loadobject();
                    $opass = $dbcccowpp->order_pass;
                    $vmid = $dbcccowpp->virtuemart_user_id;
                    $dbcccw = &JFactory::getDBO();
                    $dbcccow = "select * from `#__users` where `id` = '$vmid'";
                    $dbcccw->setQuery($dbcccow);
                    $dbcccw->query();
                    $dbcccow = $dbcccw->loadobject();
                    $refrencess = $result->refNumber;
                    $rahgiri = $Trans_ID;
                    $mm = $dbcccow->email;
                    $app = &JFactory::getApplication();
                    $sitename = $app->getCfg('sitename');
                    $subject = ''.$sitename.' - فاکتور خريد';
                    $add = JURI::base().'index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$ons.'&order_pass='.$opass;
                    $body = 'از خريد شما ممنونيم'.'<br />'.'</h1><br/><h3>شماره پيگيري شما :'.$rahgiri.'</h3><br/><h3>شماره ارجاع:'.$refrencess.'</h3>'.'<b>شماره فاکتور'.':</b>'.' '.$ons.'<br/>'.'<a href="'.$add.'">نمايش فاکتور</a>';
                    $to = [$mm];
                    $config = &JFactory::getConfig();
                    $from = [
                    $config->get('mailfrom'),
                    $config->get('fromname'), ];
                    // Invoke JMail Class
                    try {
                        $mailer = JFactory::getMailer();
                        // Set sender array so that my name will show up neatly in your inbox
                        $mailer->setSender($from);
                        // Add a recipient -- this can be a single address (string) or an array of addresses
                        $mailer->addRecipient($to);
                        $mailer->setSubject($subject);
                        $mailer->setBody($body);
                        $mailer->isHTML();
                        $mailer->send();
                    } catch (Exception $e) {
                        echo '<pre>';
                        print_r($e->getMessage());
                        echo '</pre>';
                    }
                    $cart = VirtueMartCart::getCart();
                    $cart->emptyCart();
                } else {
                    echo 'تراکنش ناموفق بود. دلیل: '.$result->message;
                }
            } else {
                echo 'تراکنش توسط کاربر لغو گردید.';
            }
        }
    }

    /*
         * Keep backwards compatibility
         * a new parameter has been added in the xml file
         */
    public function getNewStatus($method)
    {
        if (isset($method->status_pending) and $method->status_pending != '') {
            return $method->status_pending;
        } else {
            return 'P';
        }
    }

    /**
     * Display stored payment data for an order.
     */
    public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
    {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return; // Another method was selected, do nothing
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return;
        }
        VmConfig::loadJLang('com_virtuemart');

        $html = '<table class="adminlist table">'."\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('zibal_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total.' '.$paymentTable->payment_currency);
        if ($paymentTable->email_currency) {
            $html .= $this->getHtmlRowBE('zibal_EMAIL_CURRENCY', $paymentTable->email_currency);
        }
        $html .= '</table>'."\n";

        return $html;
    }

    /*	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

            if (preg_match ('/%$/', $method->cost_percent_total)) {
                $cost_percent_total = substr ($method->cost_percent_total, 0, -1);
            } else {
                $cost_percent_total = $method->cost_percent_total;
            }
            return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
        }
    */

    /**
     * Check if the payment conditions are fulfilled for this payment method.
     *
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     *
     * @return true: if the conditions are fulfilled, false otherwise
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        $this->convert_condition_amount($method);
        $amount = $this->getCartAmount($cart_prices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        //vmdebug('zibal checkConditions',  $amount, $cart_prices['salesPrice'],  $cart_prices['salesPriceCoupon']);
        $amount_cond = ($amount >= $method->min_amount and $amount <= $method->max_amount
            or
            ($method->min_amount <= $amount and ($method->max_amount == 0)));
        if (!$amount_cond) {
            return false;
        }
        $countries = [];
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = [];
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries)) {
            return true;
        }

        return false;
    }

    /*
* We must reimplement this triggers for joomla 1.7
*/

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the zibal method to create the tables.
     *
     * @author Valérie Isaksen
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     *
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel.
     *
     * @param object $cart     Cart object
     * @param int    $selected ID of the method selected
     *
     * @return bool True on succes, false on failures, null when this plugin was not selected.
     *              On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
* plgVmonSelectedCalculatePricePayment
* Calculate the price (value, tax_id) of the selected method
* It is called by the calculator
* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
* @author Valerie Isaksen
* @cart: VirtueMartCart the current cart
* @cart_prices: array the new cart prices
* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
*
*
*/

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type.
     *
     * @author Valerie Isaksen
     *
     * @param VirtueMartCart cart: the cart object
     *
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices, &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param int $order_id The order ID
     *
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     *
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * @param $orderDetails
     * @param $data
     *
     * @return null
     */
    public function plgVmOnUserInvoice($orderDetails, &$data)
    {
        if (!($method = $this->getVmPluginMethod($orderDetails['virtuemart_paymentmethod_id']))) {
            return; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return;
        }
        //vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

        if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null == 1 or $orderDetails['order_total'] > 0.00) {
            return;
        }

        if ($orderDetails['order_salesPrice'] == 0.00) {
            $data['invoice_number'] = 'reservedByPayment_'.$orderDetails['order_number']; // Nerver send the invoice via email
        }
    }

    /**
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrencyId
     *
     * @return bool|null
     */
    public function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        if (empty($payments[0]->email_currency)) {
            $vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
            $db = JFactory::getDBO();
            $q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`='.$vendorId;
            $db->setQuery($q);
            $emailCurrencyId = $db->loadResult();
        } else {
            $emailCurrencyId = $payments[0]->email_currency;
        }
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return bool True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     *
     * @author Max Milbers

     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param int $_virtuemart_order_id The order ID
     * @param int $method_id            method used for this order
     *
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     *
     * @author Valerie Isaksen
     */
    public function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
}

// No closing tag
