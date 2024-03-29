<?php
/**
 * zibal payment plugin.
 *
 * @author Jeremy Magne
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
defined('_JEXEC') or die();

$success = $viewData['success'];
$payment_name = $viewData['payment_name'];
$payment = $viewData['payment'];
$order = $viewData['order'];
$currency = $viewData['currency'];

?>
<br />
<table>
	<tr>
    	<td><?php echo vmText::_('VMPAYMENT_zibal_API_PAYMENT_NAME'); ?></td>
        <td><?php echo $payment_name; ?></td>
    </tr>

	<tr>
    	<td><?php echo vmText::_('COM_VIRTUEMART_ORDER_NUMBER'); ?></td>
        <td><?php echo $order['details']['BT']->order_number; ?></td>
    </tr>
	<?php if ($success) {
    ?>
	<tr>
		<td><?php echo vmText::_('VMPAYMENT_zibal_API_AMOUNT');
    ?></td>
        <td><?php echo $payment->mc_gross.' '.$payment->mc_currency;
    ?></td>
    </tr>

	<tr>
    	<td><?php echo vmText::_('VMPAYMENT_zibal_API_TRANSACTION_ID');
    ?></td>
        <td><?php echo $payment->txn_id;
    ?></td>
    </tr>
    <?php 
}  ?>

</table>
<?php if ($success) {
    ?>
	<br />
	<a class="vm-button-correct" href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$viewData['order']['details']['BT']->order_number.'&order_pass='.$viewData['order']['details']['BT']->order_pass, false)?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER');
    ?></a>
<?php 
} ?>
