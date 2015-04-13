=== Realtime Comments ===
Contributors: Eero Hermlin
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=W47NB7K49TJNE
Tags: comments, update, real time, real-time, realtime, update, automatic, ajax, interactive, online, chat
Requires at least: 3.0
Tested up to: 4.1.1
Stable tag: 0.7
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accepted comments from users are added to pages in real-time, without need to refresh. Makes comments 
section work interactively, like a chatroom.

== Description ==

New accepted comments from all users are added to pages in real-time, without the need to refresh the page. Allows comments section work interactively, like a chatroom. Comments re-classified as trash or spam will be dynamically removed from users screen. Using this plugin you can have really catching interactive discussions in your pages.

It's pure Wordpress plugin, no need for third parties, paid services, secondary logins. 

Administrator can choose update frequency, define in what pages realtime comments are used, comments ordering in selected pages. Additionally is possible to set custom walker function (if used theme uses it) and size of avatar.

Starting from version 0.7 it has pagination support. New top-level comments will be dynamically are added to newest comments page. Nested comments will be added to parent comments at any comments page. If top-level comments amount reaches value set in wp admin->Settings->Discussion, oldest comments start to disappear.  


== Installation ==

1. Unzip plugin to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==


== Changelog ==

= 0.7 =
Added: option to change discussion ordering for selected pages
Added: possible to change avatar size (for non-responsive themes)
Added: pagination support
Added: support for custom comments walker function
Added: custom config values for theme developers
Bugfix: first comment appearing improved
Bugfix: improved appearance of comments

= 0.6 =
* Added: option to select pages, where Realtime Comments will be activated
* Added: option to select new comments appearing order

= 0.5 =
* Initial public release

== Upgrade Notice ==

