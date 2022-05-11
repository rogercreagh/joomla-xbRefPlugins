<?php
/**
 * @package xbRefs
 * @filesource plg_content_xbrefs_script.php  
 * @version 1.9.9.7 4th February 2022
 * @desc install, upgrade and uninstall actions
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

class plgContentXbrefsconInstallerScript
{
    protected $jminver = '3.10';
    protected $jmaxver = '4.0';
    protected $deleteshortcodes = false;
    
    function preflight($type, $parent)
    {
        $app = Factory::getApplication();
        if ($type == 'uninstall') {
            $conplugin = PluginHelper::getPlugin('content','xbrefscon');
            $conpluginParams = new Registry($conplugin->params);
            $this->deleteshortcodes = $conpluginParams->get('deleteshortcodes',0);
            
            $xmlpath = JPATH_ROOT . '/plugins/content/xbrefscon/';
            $pluginXML = Installer::parseXMLInstallFile(Path::clean($xmlpath.'xbrefscon.xml'));
            $message = 'Uninstalling xbRefs - Content plugin v.'.$pluginXML['version'].' '.$pluginXML['creationDate'];
            $app->enqueueMessage($message);
        } else {
            $jversion = new Version();
            $jverthis = $jversion->getShortVersion();
            
            if (version_compare($jverthis, $this->jminver,'lt')) {
                throw new RuntimeException('xbRefs requires Joomla version '.$this->jminver. ' or higher. You have '.$jverthis);
            }
            if (version_compare($jverthis, $this->jmaxver, 'ge')){
                throw new RuntimeException('xbRefs requires Joomla version less than '.$this->jmaxver.' You have '.$jverthis);
            }      
        }
        if ($type=='update') {
            $pluginXML = Installer::parseXMLInstallFile(Path::clean(JPATH_SITE . '/plugins/content/xbrefscon/xbrefscon.xml'));
            $message = 'Updating xbRefs-Content plugin from '.$pluginXML['version'].' '.$pluginXML['creationDate'];
            $message .= ' to '.$parent->get('manifest')->version.' '.$parent->get('manifest')->creationDate;
            $app->enqueueMessage($message,'');
        }
    }
    
    function install($parent)
    {
        echo '<h3>xbRefs Content Plugin Install</h3>';
        echo '<p>For help and information see <a href="https://crosborne.co.uk/xbrefs/doc" target="_blank">
            www.crosborne.co.uk/xbrefs/doc</a></p>';
        echo '<p><i>Don\'t forget to set required options and enable the content and button plugins</i>&nbsp;';
        echo '-&nbsp;<a href="index.php?option=com_plugins&filter_search=xb" >Goto Plugin Options Pages</a></p>';
    }
    
    function uninstall($parent)
    {
        $app = Factory::getApplication();
        $found = $this->findXbrefs();
        if ($found) {
            $scmess = count($found).' articles found with {xbref...} shortcodes listed below.';
            if ($this->deleteshortcodes) {
                $scmess .= 'These have been removed';
                $app->enqueueMessage($scmess);
            } else {
                $scmess .= 'These have <b>NOT</b> been removed. <br />If you wish to remove them automatically then reinstall the xbRefs content plugin, save the options with remove on uninstall enabled and repeat the uninstall.';
                $app->enqueueMessage($scmess,'Info');
            }
        }
        echo '<p>The xbRefs Content Plugin has been uninstalled</p>';
        if ($found) {
            echo '<p>The following '.count($found).' articles contained {xbref...} shortcodes, ';
            echo '</p><ul>';
            foreach ($found as $a) {
                echo '<li><a href="'.Uri::root().'administrator/index.php?option=com_content&task=article.edit&id='.$a['id'].'" target="_blank">'.$a['title'].'</a></li>';
                if ($this->deleteshortcodes) {
                    $this->removeXbrefs($a);
                }
            }
            echo  '</ul><p>Clicking the links above will open the edit page for each article in a new tab to check for shortcodes</p>';
            echo '<p>It is suggested you do not close this page until you have checked or copied the list above somewhere safe for future use.';
            echo  '<br />If you have article versioning enabled you can use this to restore the codes if you wanted to keep them.</p>';
        } else {
            echo '<p>Articles scanned and no {xbref...} shortcodes found.';
        }
    }
    
    function update($parent) {
    }
    
    function postflight($type, $parent) {
    }

    function findXbrefs() {
        $articles = array();       
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('id','title','introtext','fulltext')));
        $query->from($db->quoteName('#__content'));
        $query->where($db->quoteName('introtext') . ' LIKE \'%{xbref%\'');
        $query->orWhere($db->quoteName('fulltext') . ' LIKE \'%{xbref%\'');
        $db->setQuery($query);
        $articles = $db->loadAssocList('id');
        return $articles;
    }
    
    function removeXbrefs(array $article) {
        $introtext = $article['introtext'];
        //stip out hide and shows
        $introtext=preg_replace('!<span class="xbhideref" (.*?)>(.*?)</span>!', '${2}', $introtext);
        $introtext=preg_replace('!<span class="xbshowref" (.*?)>(.*?)</span>!', '${2}', $introtext);
        $introtext=preg_replace('!{xbref (.*?)}(.*?){/xbref}!', '${2}', $introtext);  
        $introtext=preg_replace('!<sup class="xbref">(.*?)</sup>!', '' ,$introtext);
        //$introtext=preg_replace('!<cite class="xbref">(.*?)</cite>!', '' ,$introtext);
        $introtext=preg_replace('!{xbref-here(.*?)}!', '' ,$introtext);
        $fulltext = $article['fulltext'];
        if ($fulltext) {
            $fulltext=preg_replace('!<span class="xbhideref" (.*?)>(.*?)</span>!', '${2}', $fulltext);
            $fulltext=preg_replace('!<span class="xbshowref" (.*?)>(.*?)</span>!', '${2}', $fulltext);
            $fulltext=preg_replace('!{xbref (.*?)}(.*?){/xbref}!', '${2}', $fulltext);            
            $fulltext=preg_replace('!<sup class="xbref">(.*?)</sup>!', '' ,$fulltext);
            //$fulltext=preg_replace('!<cite class="xbref">(.*?)</cite>!', '' ,$fulltext);
            $fulltext=preg_replace('!{xbref-here(.*?)}!', '' ,$fulltext);
        }
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        // Fields to update.
        $fields = array(
            $db->quoteName('introtext') . ' = ' . $db->quote($introtext),
            $db->quoteName('fulltext') . ' = ' . $db->quote($fulltext)
        );
        
        // Conditions for which records should be updated.
        $conditions = array(
            $db->quoteName('id') . ' = ' . $article['id']
        );
        
        $query->update($db->quoteName('#__content'))->set($fields)->where($conditions);
        
        $db->setQuery($query);
        
        $result = $db->execute();
        return $result;
    }
}
