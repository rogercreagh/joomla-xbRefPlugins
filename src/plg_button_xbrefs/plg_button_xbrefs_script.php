<?php
/**
 * @package xbRefs
 * @filesource plg_button_xbrefs_script.php  
 * @version 1.9.9.9 8th February 2022
 * @desc upgrade actions
 * @author Roger C-O
 * @copyright (C) Roger Creagh-Osborne, 2022
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/
// No direct access to this file
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;

class plgEditorsxtdXbrefsbtnInstallerScript
{
    protected $jminver = '3.10';
    protected $jmaxver = '4.0';
    
    function preflight($type, $parent)
    {
        $app = Factory::getApplication();
        $jversion = new Version();
        $jverthis = $jversion->getShortVersion();
        
        if (version_compare($jverthis, $this->jminver,'lt')) {
            throw new RuntimeException('xbRefs requires Joomla version '.$this->jminver. ' or higher. You have '.$jverthis);
        }
        if (version_compare($jverthis, $this->jmaxver, 'ge')){
            throw new RuntimeException('xbRefs requires Joomla version less than '.$this->jmaxver.' You have '.$jverthis);
        }
        if ($type=='update') {
            $pluginXML = Installer::parseXMLInstallFile(Path::clean(JPATH_SITE . '/plugins/editors-xtd/xbrefsbtn/xbrefsbtn.xml'));
            $message = 'Updating xbRefs-Button plugin from '.$pluginXML['version'].' '.$pluginXML['creationDate'];
            $message .= ' to '.$parent->get('manifest')->version.' '.$parent->get('manifest')->creationDate;
            $app->enqueueMessage($message,'');
        }
    }
    
    function install($parent)
    {
    }
    
    function uninstall($parent) {
    }
    
    function update($parent) {
    }
    
    function postflight($type, $parent) {
    }

 }
