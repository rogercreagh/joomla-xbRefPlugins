<?php
/**
 * @package xbRefs-Package 
 * @subpackage xbRefs-Button Plugin
 * @filesource xbrefsbtn.php 
 * @version 2.1.1 24th April 2022
 * @desc editors-xtd plugin main php file to create button and update popup template according to settings
 * @author Roger C-O
 * @copyright (C) Roger Creagh-Osborne, 2022
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

jimport('joomla.plugin.plugin');

/**
 * Provides button to insert a reference into article as a tooltip
 */
class plgButtonXbrefsbtn extends JPlugin 
{
    protected $autoloadLanguage = true;
    protected $app;
    protected $db;
    
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
	}

/**
 * Displays the editor button.
 */
	function onDisplay($editorname, $asset, $author)
	{
	    $app = $this->app; //Factory::getApplication();
	    //only allow button on admin side
	    if (!$app->isAdmin()) 
	    {
	        return false;
	    }
 	    $extension = $app->input->get('option');
 	    //only allow button when editing article
 	    if ($extension != 'com_content') 
 	    {
            return false;     
 	    }
 	    $xbrefsconok = $this->checkComponent('xbrefscon');	    
 	    if ($xbrefsconok == 0) 
	    {
	        $app->enqueueMessage(Text::_('XBREFSBTN_NO_CONPLG_WARN'), 'Warning');
	        return false;
	    }
	    if (!$xbrefsconok) 
	    {
	        $app->enqueueMessage(Text::_('XBREFSBTN_NO_CONPLG_ERROR'), 'Error');
	        return false;
	    }
	    $conplugin = PluginHelper::getPlugin('content','xbrefscon');
 	    //check for user permissions to edit
 	    $user      = Factory::getUser();
 	    $asset = $asset !== '' ? $asset : $extension;
	    if (!($user->authorise('core.edit', $asset)
	        || $user->authorise('core.create', $asset)
	        || (count($user->getAuthorisedCategories($asset, 'core.create')) > 0)
	        || ($user->authorise('core.edit.own', $asset) && $author === $user->id)
	        || (count($user->getAuthorisedCategories($extension, 'core.edit')) > 0)
	        || (count($user->getAuthorisedCategories($extension, 'core.edit.own')) > 0 && $author === $user->id)))
	    {
	        return false;
	    }
	    $weblinksok = $this->checkComponent('com_weblinks');
	    $hideweb = ($weblinksok==1) ? '' : 'hide';
	    
	    // Get default params from the content plugin
	    $conpluginParams = new Registry($conplugin->params);	        
	    $defdisp = $conpluginParams->get('defdisp','both');
        $dispdef = '';
	    switch ($defdisp) 
	    {
	        case 'pop' : $dispdef = '(PopOver)';
	        break;
	        case 'foot' : $dispdef = '(Footnote)';
	        break;
	        case 'both' : $dispdef = '(Both)';
	        break;
	        default : $dispdef = '(NOT SET)';
	    }
	    $poptrig = $conpluginParams->get('poptrig','hover');
	    $trigdef = ($poptrig=='hover') ? '(Hover)' : '(Focus)';
	    $footheaddef = $conpluginParams->get('footertitle','');
	    
	    //the edit display and available tags list come from the button plugin params ($this)
	    $hilisc = $this->params->get('hilisc', 1); // 1=highlight shortcode, 0= no action
	    $hiliscdesc = ($hilisc==1) ? Text::_('XBREFSBTN_HILI_ON') : Text::_('XBREFSBTN_HILI_OFF');
	    $hicolour = $this->params->get('hicolour','#ffd');
	    $selectedtags = $this->params->get('taglist','');
	    $usechild = $this->params->get('usechild',1);
	    if (empty($selectedtags)) { // none selected so get all tags
	        $selectedtags = array(1); 
	        $usechild=1;
	    }
	    if ($usechild>0) {
	        $selectedtags = $this->getTagsChildren($selectedtags);
	    }
        $tagarray = $this->getTagsDetails($selectedtags, 0); //(int)$this->params->get('reqtagdesc', 1));
	    $tagoptlist = '';
        foreach ($tagarray as $t)
        {
            if ($t['id']>1) { //exclude ROOT tag 
                $tit = $t['title'];
                if (!$t['description']) 
                { //if there's no description place brackets around the title to indicate not to use it
                    $tit = '('.$tit.')';
                }
                $tit = str_repeat('- ',$t['level']) . $tit;
                $tagoptlist .= '<option value="'.$t['id'].'" >'.$tit . '</option>';               
            }
        } //endforeach $tagarray
	    
        $linkoptlist = '';
	    if ($weblinksok) {
            $selectedtags = $this->params->get('linktaglist',''); //TODO default array()
            if ((!empty($selectedtags)) && ($this->params->get('linkusechild', 1)>0)) {
                $selectedtags = $this->getTagsChildren($selectedtags);
            }
            $selectedcats = $this->params->get('linkcatlist',''); //TODO default array()
            if (!empty($selectedcats)) {
                $selectedcats = $this->getCatAndKidsIds($selectedcats);
            }
            
            $weblinks = $this->getLinksDetails($selectedtags, $selectedcats); //(int)$this->params->get('reqlinkdesc', 1));
	        foreach ($weblinks as $l)
	        {
	            $tit = $l['title'];
	            if (!$l['description'])
	            { //if there's no description place brackets around the title to indicate
	                $tit = '('.$tit.')';
	            }
	            //$tit = str_repeat('- ',$l['level']) . $tit;
	            $linkoptlist .= '<option value="'.$l['id'].'" >'.$tit . '</option>';
	        }	        
	    } //endif com_weblinks installed
	    $forcedefs = $this->params->get('forcedefs',0);
//	    $no = ($this->params->get('forcedefs')? ' style="display:none;"' : '');
	    $hide = ($forcedefs == 1)? 'hide' : '';
	    $deflist = '';
	    $deffuncs = '';
	    if ($forcedefs) 
	    {
	        $deflist = '<p>'.Text::_('XBREFSBTN_USINGDEFAULTS').'</p>';
	        $deflist .= '<ul>';
	        $deflist .= '<li>'.Text::_('XBREFSBTN_DISPLAY').' '.$dispdef.'</li>';
	        $deflist .= '<li>'.Text::_('XBREFSBTN_TRIGACT').' '.$trigdef.'</li>';
	        $deflist .= '</ul>';
	        
	    }
	    $varStrsArr =[   // These strings in the modal form will be replaced with the variable values
	        'VAR_TAGLIST'=>$tagoptlist,
	        'VAR_LINKLIST'=>$linkoptlist,
	        'VAR_DEFLIST'=>$deflist,	        
	        'VAR_DEFUNCS'=>$deffuncs,
	        'VAR_HILISC'=>$hilisc,
	        'VAR_HICOLOUR'=>$hicolour,
	        'VAR_DISPDEF'=>$dispdef,
	        'VAR_TRIGDEF' => $trigdef,	        
	        'VAR_HIDEDIV'=>$hide,
	        'VAR_SCHILIMESS'=>$hiliscdesc,
	        'VAR_FOOTHEAD'=>$footheaddef,
	        'VAR_HIDEWEB'=>$hideweb
	    ];
	    $this->preprocessTmplFile($varStrsArr);

		// add modal window and button
		HTMLHelper::_('behavior.modal');
		$button = new CMSObject; 
		$button->set('class', 'btn');
		$button->modal = true;
		$root = '../';  // Joomla expects a relative path and we are in Admin as per line 44ish
/* ***** this is for if/when we allow the button on the front end		
      if ($app->isAdmin()) { 
		    $root = '../';  // Joomla expects a relative path
		} else {
		    $root = ''; 
		}
 ***** */		
		$lang = Factory::getLanguage();
		$button->link = $root.'media/plg_button_xbrefsbtn/html/xbrefsbtn_popup.'.$lang->getTag().'.html?editor='.urlencode($editorname);
		$button->set('text', Text::_('XBREFSBTN_BUTTON_TEXT'));
		$button->name = 'quote-3'; //icon name without 'icon-' from the Joomla icomoon set. see https://docs.joomla.org/J3.x:Joomla_Standard_Icomoon_Fonts
		$button->options = "{handler: 'iframe', size: {x: 500, y: 550}}";  // must use single quotes in JSON options string
		
		return $button;
	}
	
	/**
	 * @name getTagsDetails()
	 * @desc function to get id, title and description of tag
	 * by default will only return published tags in path order
	 * @param array $tagIds
	 * @param string $order - column name to sort on (default no ordering)
	 * @param int $pub - published state value (default 1 = published)
	 * @return array
	 */
	function getTagsDetails ( $tagids, int $reqdesc = 1, string $order = 'path', int $pub = 1 ) {
	    $tagsDetails = array();
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('id','title','description','path','published','level')));
        $query->from($db->quoteName('#__tags'));
	    
        if (!empty($tagids)) {
            $tagids = ArrayHelper::toInteger($tagids);
            $query->where($db->quoteName('id') . ' IN (' . implode(',', $tagids) . ')');
	    }	    
        $query->where($db->quoteName('published') .'='. $pub);
        if ($reqdesc) {
            $query->where($db->quoteName('description') .'<>'.$db->quote(''));
        }
        if ($order) $query->order($order);
        $db->setQuery($query);
        $tagsDetails = $db->loadAssocList('id');
	    return $tagsDetails;
	}
	
	/**
	 * @name getLinksDetails()
	 * @desc Gets an array of Weblink details (id, title, url, description) filtered by optional array of tag Ids
	 * @param array|null $tagids
	 * @param int $reqdesc
	 * @param string $order
	 * @param int $pub
	 * @return null|simple array of associative arrays keyed by field names
	 */
	function getLinksDetails ( $tagids, $selectedcats, string $order = 'title', int $pub = 1 )
	{
	    $weblinks = array();	        
	        $db = Factory::getDbo();
	        $query = $db->getQuery(true);
	        $query->select($db->quoteName(array('a.id','a.title','a.description','a.url','a.state'),array('id','title','description','url','state')));
	        $query->from($db->quoteName('#__weblinks').' AS a');
	        if (!empty($selectedcats)) {
	            $query->leftJoin($db->quoteName('#__categories').' AS c ON c.id IN ('.$selectedcats.')');
	        }
	        if (!empty($tagids)) {
	            $tagfilt = ArrayHelper::toInteger($tagids);	            
                if (count($tagfilt)==1)	{ //simple version for only one tag
                    $query->join( 'INNER', $db->quoteName('#__contentitem_tag_map', 'tagmap')
                        . ' ON ' . $db->quoteName('tagmap.content_item_id') . ' = ' . $db->quoteName('a.id') )
                        ->where(array( $db->quoteName('tagmap.tag_id') . ' = ' . $tagfilt[0],
                            $db->quoteName('tagmap.type_alias') . ' = ' . $db->quote('com_weblinks.weblink') )
                            );
                } else { //more than one tag
                    // make a subquery to get a virtual table to join on
                    $subQuery = $db->getQuery(true)
                    ->select('DISTINCT ' . $db->quoteName('content_item_id'))
                    ->from($db->quoteName('#__contentitem_tag_map'))
                    ->where( array(
                        $db->quoteName('tag_id') . ' IN (' . implode(',', $tagfilt) . ')',
                        $db->quoteName('type_alias') . ' = ' . $db->quote('com_weblinks.weblink'))
                        );
                    $query->join(
                        'INNER',
                        '(' . $subQuery . ') AS ' . $db->quoteName('tagmap')
                        . ' ON ' . $db->quoteName('tagmap.content_item_id') . ' = ' . $db->quoteName('a.id')
                        );
                } //endif one/many tag
	        } //if not empty tagfilt
	        $query->where($db->quoteName('state') .'='. $pub);
	  //      if ($reqdesc) {
	  //          $query->where($db->quoteName('description') .'<>'.$db->quote(''));
	  //      }
	        if ($order) $query->order($order);
	        //group by parent?
	        $db->setQuery($query);
	        $weblinks = $db->loadAssocList('id');
	    return $weblinks;
	}
	
	public function getTagsChildren(array $tagids) {
	    $tagsHelper = new TagsHelper();
	    $alltags = array();
	    foreach ($tagids as $k)
	    {
	        $childidarray = array();
	        $tagsHelper->getTagTreeArray($k, $childidarray);
	        if (count($childidarray))
	        {
	            $alltags = array_merge($childidarray,$alltags);
	        }
	    }
	    $alltags = array_unique($alltags);
	    return $alltags;
	}
	
	public function getCatAndKidsIds($catlist) {
	    $allcats = array();
	    if (is_string($catlist)) {
	        $catlist = explode(',',$catlist);
	    }
	    $db = Factory::getDbo();
	    $query = $db->getQuery(true);
	    foreach ($catlist as $catid) {
    	    $query->select('id')->from('#__categories');
    	    $query->where('path LIKE CONCAT((SELECT path FROM #__categories WHERE id = '.$catid.'),% )');
    	    $query->order('lft ASC');
    	    array_push($allcats,...($db->loadColumn()));
	    }
	}
	
	/***
	 * checkComponent()
	 * test whether a component is installed and enabled. Sets a session variable 'com_ext_ok' to save a subsequent db call
	 * @param  $name - component name as stored in the extensions table (eg com_xbfilms)
	 * @param $usedb - if true will ignore session variable an force db check
	 * @return boolean|number - true= installed and enabled, 0= installed not enabled, false = not installed
	 */
	public function checkComponent($name) {
	    $sname=$name.'_ok';
	    $sess= Factory::getSession();
	    $ok = $sess->get($sname,false);
	    if ($ok==1) return $ok;
	    $db = Factory::getDBO();
	    $db->setQuery('SELECT enabled FROM #__extensions WHERE element = '.$db->quote($name));
	    $res = $db->loadResult();
	    $sess->set($sname,$res);
	    return $res;
	}
	
	    /**
	     * preProcessTmplFile()
	     * @param $varStrsArr - array of strings to be replaced with values
	     * In the default html form file this will replace the variable names above with their values
	     * and the language strings with translated strings and save as a new file with the current values and language.
	     * (this because the form is loaded from the editor context aand outside of joomla)
	     */
	public function preprocessTmplFile($varStrsArr) {
	    
	    //load the template file
	    $tmplstring = file_get_contents(JPATH_ROOT.'/media/plg_button_xbrefsbtn/html/xbrefsbtn_tmpl.html');
	    
	    //now go through and replace option placeholders with values
	    foreach ($varStrsArr as $paramKey=>$paramStr) {
	        $tmplstring = str_replace($paramKey,$paramStr,$tmplstring);
	    }
	    
	    //and replace the language strings with the translated values
	    //language strings used in form tmpl start with XBMODAL_ and are upper case letters and numbers with underscores
	    $pattern = '!XBMODAL_([A-Z0-9_]+)!';
	    $matches = array();
	    $cnt = preg_match_all($pattern,$tmplstring,$matches,PREG_SET_ORDER);
	    for ($i=0; $i<$cnt; $i++) {
	        $tmplstring = str_replace($matches[$i][0],Text::_(trim($matches[$i][0])),$tmplstring);
	    }
	    
	    //TOD clear block comments before saving the file
	    $pattern = '|<!--[\s\S]*?-->|';
	    $matches = array();
	    $cnt = preg_match_all($pattern,$tmplstring,$matches,PREG_SET_ORDER);
	    for ($i=0; $i<$cnt; $i++) {
	        $tmplstring = str_replace($matches[$i][0],'<!-- -->',$tmplstring);
	    }
	    
	    // save file as new name to be used in the modal iframe call (will overwrite the previously generated one
	    $lang = Factory::getLanguage();
	    file_put_contents(JPATH_ROOT.'/media/plg_button_xbrefsbtn/html/xbrefsbtn_popup.'.$lang->getTag().'.html',$tmplstring);
	    
	}
}

