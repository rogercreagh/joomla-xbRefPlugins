/**
 * @package xbRefs-Package 
 * @subpackage xbRefs-Button Plugin
 * @filesource /media/plg_button_xbrefsbtn/js/xbrefsbtn.js  
 * @version 1.9.9.7 4th February 2022
 * @desc functions for handling popup form calls to show/hide elements and submit
 * @author Roger C-O
 * @copyright (C) Roger Creagh-Osborne, 2022
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/

'use strict';

//set function depending on browser type
if (!Element.prototype.matches) {
	Element.prototype.matches =
		Element.prototype.msMatchesSelector ||
		Element.prototype.webkitMatchesSelector;
}

/*******
 * shows elements by class name
 * @param className
 * @param showType optional display:type default 'inline'
 * @returns
 */
function showElementsByClass(className, showType='inline') 
{
    var elements = document.getElementsByClassName(className);
    for (var i = 0; i < elements.length; i++) {
    	elements.item(i).style.display=showType;
    }
}
/*******
 * hides elements by class name
 * @param classname
 * @returns
 */
function hideElementsByClass(className) 
{
    var elements = document.getElementsByClassName(className);
    for (var i = 0; i < elements.length; i++) {
    	elements.item(i).style.display='none';
    }
}
/*******
 * show/hide elements by class name on chkbox change
 * @param chkbox
 * @param className of elements to show/hide
 * @param chShow optional true to show if checked, false hide if checked. default true
 * @param showType the type of display to use if showing. default inline-block
 * @description xxx
 * @returns
 */
function showHideElementsIfChecked(chkbox,className,chkShow=true, showType='inline-block')
{
	if (document.getElementById(chkbox).checked) {
		if (chkShow) {
			showElementsByClass(className,showType);
		} else {
			hideElementsByClass(className)
		}
	} else {
		if (chkShow) {
			hideElementsByClass(className);
		} else {
			showElementsByClass(className,showType)
		}
	}
}

function checkOne(chk1,chk2,show1,show2) {
	if (!document.getElementById(chk1).checked) {
		document.getElementById(chk2).checked = true;
		showElementsByClass(show2);
		hideElementsByClass(show1);
	}
	if (!document.getElementById(chk2).checked) {
		document.getElementById(chk1).checked = true;
		showElementsByClass(show1);
		hideElementsByClass(show2);
	}
}

/**
 * cleanText
 * @desc replaces single quotes with double quotes and removes all html tags except b and i
 * @param str string to be cleaned
 * @returns cleaned string
 */
function cleanText(str) {
	// convert double quotes to single
	str = str.replace(/"/g,'\'');
	// hide <b> and <i> tags which we will accept
    str = str.replace(/<\/b>/g, "@@/b@@");
    str = str.replace(/<b>/g, "@@b@@");
    str = str.replace(/<i>/g, "@@i@@");
    str = str.replace(/<\/i>/g, "@@/i@@");

    //strip out all other HTML elements
    var reg =/<(.|\n)*?>/g; 
    str = str.replace(reg,'');
    
    //put back the <b> and <i>
    str = str.replace(/@@b@@/g,"<b>");
    str = str.replace(/@@\/b@@/g,"</b>");
    str = str.replace(/@@i@@/g,"<i>");
    str = str.replace(/@@\/i@@/g,"</i>");
    
    return str;
}

function getJoomlaEditorInstance(editor) {
	let joomla = window.parent['Joomla'];
	if (joomla) {
		let editors = joomla['editors'];
		if (editors) {
			let instances = editors['instances'];
			if (instances && instances.hasOwnProperty(editor)) {
				return instances[editor];
			}
		}
	return void 0
	}
} //end function getJoomlaEditorInstance


/**
* Parses a query string into name/value pairs.
* @param {string} querystring A string of "name=value" pairs, separated by "&".
* @return {!Object<string>} An object where keys are parameter names, and value are parameter values.
*/
function fromQueryString(querystring) {
	var parameters = {};
	if (querystring.length > 1) {
		querystring.substr(1).split('&').forEach(function (keyvalue) {
			var index = keyvalue.indexOf('=');
			var key = index >= 0 ? keyvalue.substr(0, index) : keyvalue;
			var value = index >= 0 ? keyvalue.substr(index + 1) : '';
			parameters[decodeURIComponent(key)] = decodeURIComponent(value);
		});
	}
	return parameters;
} //end function fromQueryString()

/*****
* Inserts a xbref shortcode into the Joomla content editor.
* parameters passed via form fields
* shortcode = {xbref tag=N  }content{/xbref}
*/
document.addEventListener('DOMContentLoaded', function () {	
	//get the settings form when it is first loaded
	let form = document.getElementById('xbrefsbtn-settings-form');  
	// listen for the submit button
	document.getElementById('xbrefsbtn-settings-submit').addEventListener('click', function () {	
		//put the form data into an array
		/*********
		 * activetab		
		 * tagid
		 * linkid
		 * addtext
		 * addlinktext
		 * title
		 * desctext (desc)
		 * disp
		 * trig
		 * hidescode (hilisc)
		 * hicolour
		 * hideval
		 *
		 */
		var fdata = {};
		// get the values into an array indexed by name
		for ( var i = 0; i < form.elements.length; i++ ) {
		   var e = form.elements[i];
		   // for checkbox or radio only add the checked value
		   if (e.type == 'checkbox') {
			   if (e.checked) {
				   fdata[e.name] = e.value;
			   } else {
				   fdata[e.name] = '';
			   }
		   } else if (e.type == 'radio') {
			   if (e.checked) {
				   fdata[e.name] = e.value;
			   }
		   } else {
			   if (e.name) {
				   fdata[e.name] = e.value;
			   }
		   }
		}
		var tagid = '';
		var link ='';
		var linkid = '';
		var addtext = '';
		var title = '';
		var desc = '';
		var ref = '';
		var num = '';
		var head = '';
		var supnum = '';
		var activetab = fdata.activetab;
		switch (activetab) {
			case 'tag' :
				tagid = fdata.tagid;
				addtext = (fdata.addtext ? ' addtext="' + cleanText(fdata.addtext) + '"' : '');	
				ref = 'tag="'+tagid+'"' + addtext;	
				supnum = tagid;		
				break;
			case 'link':
				tagid = '0';
				linkid = fdata.linkid;
				addtext = (fdata.addlinktext ? ' addtext="' + cleanText(fdata.addlinktext) + '"' : '');	
				ref = 'tag="0" link="' + linkid + '"' + addtext;
				supnum = linkid;							
				break;
			case 'text':
				tagid = '0';
				title = 'title="' + cleanText(fdata.title) + '" ';
				desc = ' desc="' + cleanText(fdata.desctext) + '" ';
				ref = 'tag="0" ' + title + desc;	
				supnum = 'N';						
				break;
			case 'foot':
				head = ((fdata.head !='') ? ' head="' + fdata.head +'" ' : '');				
				num = ((fdata.num > 0) ? ' num="' + fdata.num + '" ' : '');
				break
		}
		//get the selected text
		var parent = window.parent;
		var selText='';
		var iframe = parent.document.getElementById("jform_articletext_ifr");
		if (iframe) { //tinyMCE and JCE use an iframe so we can get any selected text and restore it
			if (iframe.contentWindow.document.getSelection()) {
				selText = iframe.contentWindow.document.getSelection().toString();				
			}
		}
		var hilisc = fdata.hilisc;
		var highlightspan = '<span class="xbshowref" style="background-color:' + fdata.hicolour + ';">';
		var elcode = '';
		//add either a hide or highlight span depending if hidescode is set
		if (hilisc == 1) {
			elcode += highlightspan;
		}
		if (activetab == 'foot') {
			elcode += '{xbref-here' + num + head + '}';
			if (hilisc == 1) {
				elcode += '</span>';
			}
			if (selText) {
				elcode += selText + ' ';
			}			
		} else {
			if (!selText && fdata.disp === "pop") {
				alert('No selected text - popover not possible - nothing to do');
				elcode = '';
			} else {
				var tag = 'tag="' + tagid + '" ';
				var disp = ((fdata.disp != '') ? ' disp="' + fdata.disp + '"' : '' );
				var trig = ((fdata.trig != '') ? ' trig="' + fdata.trig + '"' : '' );
				if ((selText==='') || (fdata.disp==='foot')) {
					trig = '';
				}
				//build the code for the element
				//add the shortcode itself and any parameters set (only tag is required)
				elcode += '{xbref ' + ref + disp + trig + ' }';
				//close the hide/highlight span
				if (hilisc == 1) {
					elcode += '</span>';
				}
				//get any selected text in the editor (ok for TinyMCE and JCE, others not tested)
				//if we have some selected text add it in
				if (selText) {
					elcode += selText + ' ';
				}
				//add the sequence number placeholder (uses tag id for now, will be corrected by content plugin)
				if (fdata.disp !== "pop") {
					elcode += ' <sup>[' + supnum + ']</sup> ';					
				}
				if (hilisc == 1) {
					elcode += highlightspan;
				}
				//add the closing tag and close the highlight span
				elcode += '{/xbref}';
				if (hilisc == 1) {
					elcode += '</span>';
				}
			}  //endif not pop
		} //if active tab
		var editor = fromQueryString(window.location.search)['editor'];
		parent.jInsertEditorText(elcode, editor); //all editors are supposed to respond to this overwriting any selected text
		parent.jModalClose();
		
	}); //end document.getElementById('xbrefsbtn-settings-submit').addEventListener
}); // end document.addEventListener

/******
 * tab functions
 */
 function openTab(evt, tabName) {
  // Declare all variables
  	var i, tabcontent, tablinks;

 	document.getElementById('activetab').value = tabName;

  // Get all elements with class="tabcontent" and hide them
  	tabcontent = document.getElementsByClassName("tabcontent");
  	for (i = 0; i < tabcontent.length; i++) {
    	tabcontent[i].style.display = "none";
  	}
  
  //special case for tabName==foot - hide/show default values
  	if (tabName === 'foot') {
		document.getElementById('hideftdiv').style.visibility = "hidden";
	} else {
		document.getElementById('hideftdiv').style.visibility = "visible";	
	}

  // Get all elements with class="tablinks" and remove the class "active"
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }

  // Show the current tab, and add an "active" class to the button that opened the tab
  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.className += " active";
}

