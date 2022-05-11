<?php
/**
 * @package xbRefs-Package 
 * @filesource pkg_xbrefsplugins_script.php  
 * @version 2.1.1 2nd May 2022
 * @desc install, upgrade and uninstall actions
 * @author Roger C-O
 * @copyright (C) Roger Creagh-Osborne, 2022
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/
// No direct access to this file
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Registry\Registry;

class pkg_xbrefspluginsInstallerScript
{
    protected $jminver = '3.10';
    protected $jmaxver = '4.0';
    protected $deleteshortcodes = false;
    
    function preflight($type, $parent)
    {
        if ($type == 'uninstall') {
            //get the deleteshortcodes flag before it gets destroyed
            $conplugin = PluginHelper::getPlugin('content','xbrefscon');
            $conpluginParams = new Registry($conplugin->params);
            $this->deleteshortcodes = $conpluginParams->get('deleteshortcodes',0);
        } else {
            //check Joomla version
            $jversion = new Version();
            $jverthis = $jversion->getShortVersion();
            
            if (version_compare($jverthis, $this->jminver,'lt')) {
                throw new RuntimeException('xbRefs requires Joomla version '.$this->jminver. ' or higher. You have '.$jverthis);
            }
            if (version_compare($jverthis, $this->jmaxver, 'ge')){
                throw new RuntimeException('xbRefs requires Joomla version less than '.$this->jmaxver.' You have '.$jverthis);
            }                   
        }
    }
    
    function install($parent)
    {
        echo '<div style="padding: 7px; margin: 15px; list-style: none; -webkit-border-radius: 4px; -moz-border-radius: 4px;
		border-radius: 4px; background-image: linear-gradient(#fffff7,#ffffe0); border: solid 1px #830000; color:#830000;">';
        echo '<h3>xbRefs Plugins Package installed</h3>';
        echo '<p>Package version '.$parent->get('manifest')->version.' '.$parent->get('manifest')->creationDate.'<br />';
        echo 'Extensions included: </p>';
        echo '<ul><li>xbRefs-Button '.$parent->get('manifest')->btn_version.' editors-xtd plugin for button to insert {xbref...} shortcode into article</li>';
        echo '<li> xbRefs-Content '.$parent->get('manifest')->con_version.' content plugin to process {xbref...} shortcodes generating popups and footnotes as specified</li></ul>';
        echo '<p><i>For help and information see <a href="https://crosborne.co.uk/xbrefs/doc" target="_blank">
            www.crosborne.co.uk/refs/doc</a></i></p>';
        echo '<p><b>Don\'t forget to set required options and enable the content and button plugins</b>&nbsp;';
        echo '-&nbsp;<a href="index.php?option=com_plugins&filter_search=xb" class="btn btn-small btn-info">Plugin Options Pages</a></p>';
        echo '</div>';
    }
    
    function uninstall($parent)
    {
        echo '<p>The xbRefs Plugins Package has been uninstalled</p>';
        $found = $this->findXbrefs();
        if ($found) {
            echo '<p>The following '.count($found).' articles contained {xbref...} shortcodes, there may be more than one in each article.';
            echo '</p><ul>';
            foreach ($found as $a) {
                echo '<li><a href="'.Uri::root().'administrator/index.php?option=com_content&task=article.edit&id='.$a['id'].'" target="_blank">'.$a['title'].'</a></li>';
                if ($this->deleteshortcodes) {
                    $this->removeXbrefs($a);
                    Factory::getApplication()->enqueueMessage('shortcode deleted','warn');
                }
            }
            echo  '</ul><p>Clicking the links above will open the edit page for each article in a new tab to check for shortcodes</p>';
            echo '<p>It is suggested you do not close this page until you have checked or copied the list above somewhere safe for future use.';
            echo  '<br />If you have article versioning enabled you can use this to restore the codes if you wanted to keep them.</p>';
        } else {
            echo '<p>Articles scanned and no {xbref...} shortcodes found.';
        }
    }
    
    function update($parent)
    {        
        echo '<div style="padding: 7px; margin: 15px; list-style: none; -webkit-border-radius: 4px; -moz-border-radius: 4px;
		border-radius: 4px; background-image: linear-gradient(#fffff7,#ffffe0); border: solid 1px #830000; color:#830000;">';
        echo '<p>xbRefs Package updated to version ' . $parent->get('manifest')->version . ' including plugins</p>';
        echo '<ul><li>xbRefs-Button version ' . $parent->get('manifest')->btn_version . '</li>';
        echo '<li>xbRefs-Content version ' . $parent->get('manifest')->con_version . '</li></ul>';
        echo '<p>For details see <a href="http://crosborne.co.uk/xbrefs/changelog" target="_blank">
            www.crosborne.co.uk/xbrefs/changelog</a></p>';
        echo '<hr /><h4>Announcement</h4>';
        echo '<p><b>xbRefMan</b> is now available.  xbRefMan is a component to manage your references in the admin back-end to your site';
        echo ' and also provides views to list references used by article, and articles by reference used on the public site.';
        echo '<br />To try xbRefMan you can download and install it directly from the <a href="https://crosborne.uk/downloads?download=22">CrOsborne website</a>.</p>';
        echo '<hr /><p style="font-size:0.9em;font-style:italic;">NB this update has no changes to the plugins themselves, it is only to advise you of xbRefMan.</p>';
        echo '</div>';
    }
    
    function postflight($type, $parent)
    {
        $message = $parent->get('manifest')->name;
        switch ($type) {
            case 'install': $message .= ' version '.$parent->get('manifest')->version.' has been installed';
            break;
            case 'uninstall': $message .= ' has been uninstalled'; break;
            case 'update': $message .= ' has been updated to version '.$parent->get('manifest')->version; 
            break;
            case 'discover_install': $message .= 'discovered and installed version '.$parent->get('manifest')->version; break;
        }
        Factory::getApplication()->enqueueMessage($message);
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
        $introtext=preg_replace('!<sup class="xbrefed">(.*?)</sup>!', '' ,$introtext);
        $introtext=preg_replace('!<cite class="xbrefed">(.*?)</cite>!', '' ,$introtext);
        $fulltext = $article['fulltext'];
        if ($fulltext) {
            $fulltext=preg_replace('!<span class="xbhideref" (.*?)>(.*?)</span>!', '${2}', $fulltext);
            $fulltext=preg_replace('!<span class="xbshowref" (.*?)>(.*?)</span>!', '${2}', $fulltext);
            $fulltext=preg_replace('!{xbref (.*?)}(.*?){/xbref}!', '${2}', $fulltext);            
            $fulltext=preg_replace('!<sup class="xbrefed">(.*?)</sup>!', '' ,$fulltext);
            $fulltext=preg_replace('!<cite class="xbrefed">(.*?)</cite>!', '' ,$fulltext);
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
