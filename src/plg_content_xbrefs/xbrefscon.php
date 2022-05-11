<?php
/**
 * @package xbRefs-Package 
 * @subpackage xbRefs-Content Plugin
 * @filesource xbrefscon.php 
 * @version 2.1.0.2 31st March 2022
 * @author Roger C-O
 * @copyright (C) Roger Creagh-Osborne, 2022
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @desc if {xbref...} shortcode present in article strips xbshowref & xbhideref spans,
 *      parses shortcode and generates bootstrap popup code and/or footnote to article
 *      optionally adds any joomla tags used as reference content to the article as article tags
**/

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class plgContentXbrefscon extends CMSPlugin {        

    function __construct(& $subject, $config)
    {
//        $lang = Factory::getLanguage();
//        $lang->load('plg_content_xbrefscon', JPATH_ADMINISTRATOR);
        $this->loadLanguage('plg_content_xbrefscon');
        parent::__construct($subject, $config);
    }
    
    public function onContentPrepare($context, &$article, &$params, $limitstart = 0) {
        
        //only use on site side
        if (Factory::getApplication()->isClient('adninistrator')) {
            return true;
        } 
        if ($context == 'com_finder.indexer') {
            return true;  //don't bother if just indexing
        }
        // quick check if showtcode may be present
        if (false === strpos($article->text, '{xbref')) {
            return true; //don't bother if no {xbref tags
        }

        $weblinks_ok = $this->checkComponent('com_weblinks')==1;
        
        // get defaults for shortcode from options
        $defdisp = $this->params->get('defdisp','foot'); // pop|both|foot
        $deftrig = $this->params->get('deftrig','hover');    // hover|focus|click
        // get other option values
        $refbrkt = $this->params->get('refbrkt', 1 );   // 0|1
        $clickhelp = $this->params->get('clickhelp',1);
        $clickprompt = "<div class='clickprompt'>".Text::_('XBREFSCON_CLICK_PROMPT')."</div>";       
        $weblinktarg = $this->params->get('weblinktarg', 2 );
        $weblinkpos = $this->params->get('weblinkpos', 1 );
//        $forceclick = $this->params->get('forceclick', '0');
        $linktrig = $this->params->get('linktrig', '0');
        $foothdtext = $this->params->get('foothdtext','');
        $fthdfontsize = $this->validateCssSize($this->params->get('fthdfontsize'),'1.1em');
        $footfontsize = $this->validateCssSize($this->params->get('footfontsize'),'0.9em');
        $footcolour =  $this->params->get('footcolour','');
        $footacolour =  $this->params->get('footacolour','');
        $footbg = $this->params->get('footbg','');
        $footborder = $this->params->get('footborder','');
        if (is_array($footborder)) {
            $top = in_array('top', $footborder);
            $rgt = in_array('rgt', $footborder);
            $bot = in_array('bot', $footborder);
            $lft = in_array('lft', $footborder);
        }
        /* class names used - partially defined in xbrefscon.css and partiall defined by options below */
        $footclass = 'xbreffooter';
        $foothdclass = 'xbreffthead';
        $citenameclass = 'xbrefcitename';
        $refsupclass = 'xbrefsup';
        $trigclassarr= array('hover'=>'xbhover','focus'=>'xbfocus','click'=>'xbclick');
        $popselclass = 'xbpop'; 
        $xbrefpop = 'xbrefpop';
        
        //add the stylesheet xbrefs.css and script xbrefs.js (for bootstrap trigger) if required
        $document = Factory::getDocument();
//        HTMLHelper::_('jquery.framework');
        HTMLHelper::_('bootstrap.framework');
        $document->addStyleSheet(Uri::base(). 'media/plg_content_xbrefscon/css/xbrefscon.css');
        HTMLHelper::_('script', 'plg_content_xbrefscon/xbrefscon.js', array('version' => 'auto', 'relative' => true));
        
        //add colours etc to stylesheet
        $addstyle = '';
        /* Trigger cues */
        $addstyle .= '.xbhover, .xbhover:hover {text-decoration: underline '.$this->params->get('hovercol','').' '.$this->params->get('hoverline','').';}';
        $addstyle .= '.xbfocus, .xbfocus:hover {text-decoration: underline '.$this->params->get('focuscol','').' '.$this->params->get('focusline','').';}';
        $addstyle .= '.xbclick, .xbclick:hover {text-decoration: underline '.$this->params->get('clickcol','').' '.$this->params->get('clickline','').';}';
        /* Reference superscript link */
        $addstyle .= '.xbrefsup a {color:'.$footcolour.';}';
        /* Footer div */
        $footstyle = 'font-size:'.$footfontsize.';color:'.$footcolour.';background-color:'.$footbg.';';
        $footstyle .= ($top) ? 'border-top:solid 1px '.$footcolour.';': '';
        $footstyle .= ($rgt) ? 'border-right:solid 1px '.$footcolour.';': '';
        $footstyle .= ($bot) ? 'border-bottom:solid 1px '.$footcolour.';': '';
        $footstyle .= ($lft) ? 'border-left:solid 1px '.$footcolour.';': '';
        $addstyle .= '.xbreffooter {'.$footstyle.'}';
        if ($footacolour) {
            $addstyle .= '.xbreffooter a {color:'.$footacolour.'}';
        }       
        /* Footer header */
        $addstyle .= '.xbreffthead {font-size: '.$fthdfontsize.'; }';
        /* Popover title - background made darker */
        $poptitbg = $this->hex2RGB($footbg,true,-16);
        $addstyle .= '.xbrefpop + .popover > .popover-title {background-color:'.$poptitbg.' !important;color:'.$footcolour.';border-bottom-color:'.$footcolour.';}';
        /* Popover content */
        $addstyle .= '.xbrefpop  + .popover > .popover-content {background-color:'.$footbg.' !important;color:'.$footcolour.';}';
        /* Popover Arrows */
        $addstyle .= '.xbrefpop + .popover.right>.arrow:after { border-right-color: '.$footcolour.'; }';
        $addstyle .= '.xbrefpop + .popover.left>.arrow:after { border-left-color: '.$footcolour.'; }';
        $addstyle .= '.xbrefpop + .popover.bottom>.arrow:after { border-bottom-color: '.$footcolour.'; }';
        $addstyle .= '.xbrefpop + .popover.top>.arrow:after { border-top-color: '.$footcolour.'; }';
        $document->addStyleDeclaration($addstyle);      

        //  setup variables
        $footcnt = 0; //the count of footer references one the page - reset when output
        $olstart = 1; // the current starting point for numbering in footer, will increase if an intermediate footer is inserted
        $refnum = 1; // increment every time a footer reference is added
//        $setnum = 0; // set if xbref-here num=N is found
        $footitems=''; // the citations as <li> strings list
        $idx= 0;
        //get the article text
        $articleText = $article->text;
        
        //strip out xbshowref if present leaving enclosed content 
        $articleText=preg_replace('!<span class="xbshowref".*?>(.*?)</span>!', '${1}', $articleText);
        
        //  get array of {xbref ...}...{/xbref} or {xbref-her...} shortcodes and contents
        $matches = array();
        preg_match_all('!{xbref (.*?)}(.*?){/xbref}|{xbref-here ?(.*?)}!', $articleText, $matches, PREG_SET_ORDER);
        // process all the found shortcodes
        foreach ($matches as $ref) { 
            //we'll do any {xbref-here's first as they may be setting a start num for subsequent refs to use
            if (substr($ref[0],0,11) == '{xbref-here' ) {
                // if context is not article we'll just remove the shortcode. Could enable other components here
                if ($context == 'com_content.article') {
                    //$ref[3] will contain and num= and head= values
                    $num = $this->getNameValue('num',$ref[3]);
                    // have we got any items ready to process?
                    if ($footcnt) {
                        // we have footer info ready & waiting so insert it
                        $head = $this->getNameValue('head',$ref[3]);
                        $head = ($head!='') ? $head : $foothdtext;
                        $footer = '<div class="'.$footclass.'">';
                        $footer .= '<div class="'.$foothdclass.'">'.$head.'</div><ol type="1" start="'.$olstart.'">'.$footitems.'</ol></div>';
                        $articleText = str_replace($ref[0],$footer,$articleText);
                        // clear and reset footer content
                        $footitems = '';
                        $olstart += $footcnt;
                        $footcnt = 0;
                    } else {
                        //we've no items for the footer so clear the shortcode
                        $articleText = str_replace($ref[0],'',$articleText);
                    }
                    // having inserted a footer area we now set the start number for the next footer area
                    if (($num>0) && ($num>$olstart)) {
                        // if num is set AND it is greater than the next start value
                        $olstart = $num;
                        $refnum = $num;
                    }
                } else {
                    // not an article so clear this shortcode
                    $articleText = str_replace($ref[0],'',$articleText);
                }
            } else {
                // $ref[1] contains the text {xbref text }, $ref[2] contains the content {xbref ...}content{/xbref}
                $ref[1] .= ' '; //make sure we have a space at the end - getNameValue() needs it
                $content = $ref[2];
                //strip it out any placeholders for dummy ref link added by button
                $content = preg_replace('!<sup(.+?)</sup>!', '', $content);
                // at this point $content will only contain any text enclosed in the shortcode
                $content = trim($content);
                //if we are in com_content_article parse the shortcode, otherwise we simply remove it. Could allow other components here
                $ok = false;
                if ($context == 'com_content.article') {
                    $citename = '';
                    $citedesc = '';
                    $tagid = $this->getNameValue('tag',$ref[1]);
                    //if com_weblinks not available set any linkid to zero
                    $linkid = ($weblinks_ok) ? $this->getNameValue('link',$ref[1]) : 0;
                    // if we have a tagid we'll do that, elseif we have a linkid, else it must be text
                    if ($tagid > 0) {
                        $tag = $this->getTagDetails($tagid);
                        if ($tag) {
                            $idx ++;
                            $ok = true;
                            $citename = $tag['title'];
                            $citedesc = $tag['description'];
                            // add any addtext to the end of the description
                            $citedesc .= ' '.$this->getNameValue('addtext',$ref[1]);
                        } else {
                            // else invalid tagid so clear details
                            $citename = '';
                            $citedesc= '';                         
                        }
                    } elseif ($linkid>0) {
                        $link = $this->getLinkDetails($linkid);
                        if (!empty($link)){   
                            $idx ++;
                            $ok = true;
                            $citename = $link['title'];
                            $targ = 'target=';
                            //for now g=hard coded to blank. need option to set or use weblinks setting
                            //if weblinks setting need to decode params and fetch global if req
                            switch ($weblinktarg) {
                                case 1:
                                    $targ .= '"_blank"';
                                    break;
                                case 2:
                                    //$host = parse_url($link['url'],PHP_URL_HOST);
                                    if (Uri::isInternal($link['url'])) {
                                        $targ = '';
                                    } else {
                                        $targ .= '"_blank"';
                                    }
                                    break;                               
                                default:
                                    $targ='';
                                break;
                            }
                            $append = '';
                            switch ($weblinkpos) {
                                case 1: // append Visit...
                                    $append = ' <a href="'.$link['url'].'" '.$targ.'><i>Visit '.$link['title'].'</i></a>';
                                    break;
                                case 2: // append url
                                    $append = ' <a href="'.$link['url'].'" '.$targ.'>'.$link['url'].'</a>';
                                    break;
                                case 3:  //use title
                                    $citename = '<a href="'.$link['url'].'" '.$targ.'>'.$citename.'</a>';
                                    break;
                                default:
                                    break;
                            }
                            $citedesc = $link['description'].' '.$this->getNameValue('addtext',$ref[1]).' '.$append;                       
                        } //endif not empty link
                    } else {
                        $citename = $this->getNameValue('title',$ref[1]);
                        if ($citename) {
                            $idx ++;
                            $ok = true;
                            $citedesc = $this->getNameValue('desc',$ref[1]);
                            if ($citedesc =='') {
                                //try the old format - remove this after v2
                                $citedesc = $this->getNameValue('desctext',$ref[1]);
                            }
                            //clean most html and quotes from text description
                            $citedesc = $this->cleanText($citedesc,'<b><i><em><h4>',true,false); 
                             
                        }
                   }
                     
                    if ($ok) { // && ($citedesc)) { 
                        //okay to proceed, otherwise just remove shortcode
                        $poptrig = $this->getNameValue('trig',$ref[1]);
                        if ($poptrig=='') $poptrig = $deftrig;                   
                        $disp = $this->getNameValue('disp',$ref[1]);
                        if ($disp=='') $disp = $defdisp;
                                          
                        $content = '<a id="refid'.$idx.'"></a>'.$content;
                        
                        if ($disp != 'foot') {
                            // we need to make a popover
                            //popover will only work if we have content 
                            if ($content) {
                                //poppos= is not available in button but could be entered manually to force left/right/bottom popover 
                                $poppos = $this->getNameValue('poppos',$ref[1]);
                                if ($poppos=='') $poppos = 'top';
                                // if we are doing a link we might be enforcing click action trigger
                                $desclink = strpos(strtolower($citedesc),'<a href=');
                                if ( (($linkid>0) && ($linktrig)) || (($desclink!==false) && ($linktrig)) ) {
                                    $poptrig = $linktrig;
                                }
                                //build the span to wrap the selected text $content
                                $popspan = '<span tabindex="'.$refnum.'" class="'.$popselclass.' '. $xbrefpop.' '.$trigclassarr[$poptrig].'" ';
//                                $popspan .= 'style="color:'.$popcolarr[$poptrig].';" ';
                                $prompttext = (($poptrig=='click') && ($clickhelp==1)) ? $clickprompt : '';
                                $popspan .= ' data-trigger="'.$poptrig.
                                    '" title="'.$this->cleanText($citename,'<a>',false,true).
                                    '" data-content="'.$this->cleanText($citedesc,true,true,true).$prompttext.
                                    '" data-placement="'.$poppos.'" >';
                                $content = $popspan.$content.'</span>';     
                            }
                        }
                        
                        if ($disp != 'pop') {
                            //we need to add the ref number in superscript
                            $content .= '<sup class="'.$refsupclass.'"><a href="#ref'.$refnum.'">';
                            $content .= (($refbrkt)? '[': '').$refnum.(($refbrkt)? ']': '');
                            $content .= '</a></sup>';
                            // we need to add to footer to the list
                            // the name might include a link
                            $citename = $this->cleanText($citename,'<a>',false,false);
                            // we'll allow whatever html might be in the description - we need to replace <p>...</p> with ...<br /> 
                            $citedesc = $this->cleanText($citedesc,true,true,false);
                            $footitems .= '<li><a id="ref'.$refnum.'"></a><a href="#refid'.$idx.'" style="color:'.$footcolour.'">&#9111;</a><span class="'.$citenameclass.'"> '.$citename.'</span> ';
                            $footitems .= ': '.$citedesc.'</li>';                      
                        }
                        if ($disp != 'pop') {
                            $refnum ++;
                            $footcnt ++;
                        }
                    } // endif we have citeation title and desc
                } //  end if content article
                // replace the shortcode and content with the new content
                // if this context is not com_content.article this will simply remove the shortcode leaving $content
                $articleText = str_replace($ref[0],$content,$articleText);                
            } // endif else xbfer-here
        } //end foreach $matches
        // if we have any items for the footnotes not already handled by {xbref-here then append div and footer to article
        if ($footcnt) {
            $footer = '<div class="'.$footclass.'">';
            $footer .= '<div class="'.$foothdclass.'">'.$foothdtext.'</div><ol type="1" start="'.$olstart.'">'.$footitems.'</ol></div>';
            $articleText .= $footer;
        }
        
        //finally write the corrected text back to the article object
        $article->text = $articleText;
        //and clear the weblinks session value just in case it gets uninstalled before the front-end session expires
        Factory::getSession()->set('com_weblinks_ok',null);
        return '';
        
    } //onContentPrepare
    
    /**
     * getNameValue 
     * @desc return the value from a substring name="value" in the source string.
     *      value must be in quotes and no spaces between name and value. value cannot contain quotes
     * @param string $name - the named value to return
     * @param string $source - the string which may contain name="value"
     * @return string - value or '' if name=" not found
     */
    function getNameValue (string $name, string $source) {
        $match = array();
        return ((preg_match('!'.$name.'="(.*?)" !',$source, $match)) ? $match[1] : '');
    }
    
    /**
     * cleanText
     * @desc removes unwanted tags from text to make it compatible with popover and reduce space for footer
     *      p is replaced with br at end of para
     * @param string $text - the text to be cleaned and returned
     * @param mixed $allowtags - true to allow all tags, false strip all, or string of tags to allow
     *      (NB self-closing tags like br and hr are allowed in any event)
     * @param bool $p2br default true : convert p to br and preserve
     * @param bool $d2squote default true : replace double quote with &quot; quote
     * @return string - the cleaned text
     */
    function cleanText (string $text, $allowtags, bool $p2br = true, bool $d2squote = true) {
        if ($p2br)
        {
            // replace <p>...</p> with ...<br />
            $text = trim(str_replace(array('<p>','</p>'),array('','<br />'),$text));
            //strip off a trailing <br /> if we have one left
            $lastbr = strrpos($text,'<br />');
            if ($lastbr===strlen($text)-6) $text = substr($text, 0, $lastbr);
        }
        if ($allowtags !== true) $text = strip_tags($text, $allowtags);
        if ($d2squote) {
            // replace double quotes 
            $text = str_replace('"','&quot;',$text);
        }
        return $text;
    }
    
    /**
     * getTagDetails
     * @param int $tagId
     * @param int $pub default = 1 (published)
     * @return array
     */
    function getTagDetails($tagId, int $pub = 1) {
        $tagDetails = array();
        //TODO amend this to allow find by alias or id
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('id','title','description','alias','path','published')));
        $query->from($db->quoteName('#__tags'));
        $query->where($db->quoteName('id') . ' = ' . $tagId );
        if ($pub!==false) {
            $query->where($db->quoteName('published') .'='. $pub);       
        }
        $db->setQuery($query);
        $tagDetails = $db->loadAssoc();
        return $tagDetails;
    }
    
    /**
     * getLinkDetails
     * @param int $linkId
     * @param int $pub default = 1 (published)
     * @return array
     */
    function getLinkDetails($linkId, int $pub = 1) {
        //check com_weblinks installed else return false
        $linkDetails = array();
        // amend this to allow find by alias or id
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('id','title','description','alias','url','state','params')));
        $query->from($db->quoteName('#__weblinks'));
        $query->where($db->quoteName('id') . ' = ' . $linkId );
        if ($pub!==false) {
           $query->where($db->quoteName('state') .'='. $pub);
        }
        $db->setQuery($query);
        $linkDetails = $db->loadAssoc();
        return $linkDetails;
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
     * Convert a hexa decimal color code to its RGB equivalent
     *
     * @param string $hexStr (hexadecimal color value)
     * @param boolean $returnAsString (if set true, returns 'rgb(R,G,B)'. Otherwise returns associative array)
     * @param mixed $offset value to be added to each element (can be negative)
     * @return array or string (depending on second parameter. Returns False if invalid hex color value, string may contain values <0 or >255)
     */
    function hex2RGB($hexStr, $returnAsString = false, $offset = 0) {
        $hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr); // Gets a proper hex string
        $rgbArray = array();
        if (strlen($hexStr) == 6) { //If a proper hex code, convert using bitwise operation. No overhead... faster
            $colorVal = hexdec($hexStr);
            $rgbArray['red'] = 0xFF & ($colorVal >> 0x10);
            $rgbArray['green'] = 0xFF & ($colorVal >> 0x8);
            $rgbArray['blue'] = 0xFF & $colorVal;
        } elseif (strlen($hexStr) == 3) { //if shorthand notation, need some string manipulations
            $rgbArray['red'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
            $rgbArray['green'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
            $rgbArray['blue'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
        } else {
            return false; //Invalid hex color code
        }
        if ($returnAsString) {
            if ($offset!==0) {
                if (!is_array($offset)) {
                    $offset = array('red'=>$offset,'green'=>$offset,'blue'=>$offset);
                }
                $rgbArray['red'] = $offset['red'] + $rgbArray["red"];
                $rgbArray['blue'] = $rgbArray['blue'] + $offset['blue'];
                $rgbArray['green'] = $offset['green'] + $rgbArray['green'];
            }
            return 'rgb('.implode(',',$rgbArray).')';   
        }
        return $rgbArray; // returns the rgb string or the associative array
    }
    
    
    function validateCssSize($sizestr, $def='1em') {
        if ($sizestr == '') {
            $sizestr = $def;
        } else {
            if (!((is_numeric(substr($sizestr,0,-2))) && (in_array(substr($sizestr,-2),array('pt','px','em'))))) {
                $sizestr = $def;
            }
        }
        return $sizestr;
    }
    
} //plgContentXbrefscon
