# joomla-xbRefPlugins
**Insert References and Citations in Joomla articles.**

Button (editors-xtd) and Content plugins for references and footnotes in articles

Current release is v2.1.1. Download as a Joomla installation package from [CrOsborne website](https://crosborne.uk/downloads/file/21-xbrefs-plugins). The package contains both plugins.

References are inserted in an article using a shortcode

 `{xbref [ref specs]}[optional text for popover]{/xbref}`

Reference title and citation can be specified using a Joomla tag, a Joomla Weblinks Component item, or embedded in the shortcode.

A reference may be displayed either as a numbered footnote to the article (as in a book), or as popover text (easier for the user of a webpage than jumping to the end and back) or as both (best for articles that may be printed or read on your website.

The xbRefs-Button plugin makes entering references easy rather having to type the relevant `[ref specs]` manually.

The conversion of shortcodes embedded in the article text to nicely formatted footnotes and popovers is handled by the xbRefs-Content plugin.

For references flagged as a footnote a footnote area is inserted at the end of the article text and superscript index numbers are inserted in the text where the shorcode was.

For references flagged as a popover the plugin will ensert code for a bootstrap popover triggered by either mouse hover or click. A click trigger is often better for references which contain links that the user may wish to follow, and for touch screen devices.

For full documentation see the website [https://crosborne.uk/xbrefs]

A companion component [**xbRefMan**](https://github.com/rogercreagh/joomla-xbRefMan) is now also available which provides backend management of references on a site and some from end views for listing/indexing references. See project here and full information at [https://crosborne.uk/refman]
