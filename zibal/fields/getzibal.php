<?php
/**
 * Zibal payment plugin.
 *
 * @author Valerie Isaksen
 *
 * @version $Id$
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
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');
class JFormFieldGetzibal extends JFormField
{
    /*
     * Element name
     *
     * @access    protected
     * @var        string
     */
    public $type = 'getzibal';

    protected function getInput()
    {
        JHtml::_('behavior.colorpicker');

        vmJsApi::addJScript('/plugins/vmpayment/zibal/zibal/assets/js/admin.js');
        vmJsApi::css('zibal', 'plugins/vmpayment/zibal/zibal/assets/css/');

        $url = 'https://zibal.ir/';
        $logo = '<img src="plugins/vmpayment/zibal/zibal/assets/images/logo.png" />';
        $html = '<p><a target="_blank" href="'.$url.'"  >'.$logo.'</a></p>';
        $html .= '<p><a target="_blank" href="'.$url.'" class="signin-button-link">'.vmText::_('VMPAYMENT_zibal_REGISTER').'</a>';
        $html .= ' <a target="_blank" href="http://docs.virtuemart.net/manual/shop-menu/payment-methods/zibal.html" class="signin-button-link">'.vmText::_('VMPAYMENT_zibal_DOCUMENTATION').'</a></p>';

        return $html;
    }
}
