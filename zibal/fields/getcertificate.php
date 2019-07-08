<?php

defined('_JEXEC') or die();
/*
 *
 * @package    VirtueMart
 * @subpackage Plugins  - Elements
 * @author Valérie Isaksen
 * @link http://www.virtuemart.net
 * @copyright Copyright (c) 2004 - 2011 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * @version $Id:$
 */

JFormHelper::loadFieldClass('filelist');
class JFormFieldGetcertificate extends JFormFieldFileList
{
    /*
     * Element name
     *
     * @access    protected
     * @var        string
     */
    public $type = 'Getcertificate';


    protected function getOptions()
    {
        $options = [];
        $folder = $this->directory;
        $safePath = VmConfig::get('forSale_path', '');

        $certificatePath = $safePath.$folder;
        $certificatePath = JPath::clean($certificatePath);

        // Is the path a folder?
        if (!is_dir($certificatePath)) {
            return '<span>'.vmText::sprintf('VMPAYMENT_zibal_CERTIFICATE_FOLDER_NOT_EXIST', $certificatePath).'</span>';
        }
        $path = str_replace('/', DS, $certificatePath);

        // Prepend some default options based on field attributes.
        if (!$this->hideNone) {
            $options[] = JHtml::_('select.option', '-1', JText::alt('JOPTION_DO_NOT_USE', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));
        }

        if (!$this->hideDefault) {
            $options[] = JHtml::_('select.option', '', JText::alt('JOPTION_USE_DEFAULT', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));
        }

        // Get a list of files in the search path with the given filter.
        $files = JFolder::files($path, $this->filter);

        // Build the options list from the list of files.
        if (is_array($files)) {
            foreach ($files as $file) {
                // Check to see if the file is in the exclude mask.
                if ($this->exclude) {
                    if (preg_match(chr(1).$this->exclude.chr(1), $file)) {
                        continue;
                    }
                }

                // If the extension is to be stripped, do it.
                if ($this->stripExt) {
                    $file = JFile::stripExt($file);
                }

                $options[] = JHtml::_('select.option', $file, $file);
            }
        }

        return $options;
    }
}
