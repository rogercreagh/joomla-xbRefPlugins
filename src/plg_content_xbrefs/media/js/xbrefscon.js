/**
 * @package xbRefs 
 * @version media/js/xbrefs.js 1.9.0 23rd January 2022
 * @desc 
 * @author Roger C-O
 * @copyright (C) Roger Creagh-Osborne, 2022
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * 
**/
jQuery(function($) {
	"use strict";
	initTooltip();
  
	function initTooltip(event, container)
	{
		$(container || document).find('.xbpop').popover();
	}

});
