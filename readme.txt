=== Revisions ===
Contributors: Paul Menard
Donate link: http://www.codehooligans.com
Tags: Revision, versioning, admin, content, editor
Requires at least: 2.0.3
Tested up to: 2.5.1
Stable tag: 1.8.3

== Description ==

Provide Versioning, Preview and Rollback ability on Pages and Posts. 

[Plugin Homepage](http://www.codehooligans.com/2008/04/18/versioning-your-blog-content/ "Versioning Your Blog Content")

Version History:
1.8 2008-05-03: Major work to post/page pre-processing. Added some UI bling.

== Installation ==

1. Upload `Revisions` folder and contained files to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Write -> Post or Write -> Page.

== Frequently Asked Questions ==

= What Post fields are versioned? =

At the moment only the content itself is saved. I've had requests for categories, tags and meta information. I will be adding revision support for title and meta in the next major release.

== Screenshots ==

1. Post/Page Admin interface in WordPress 2.5.x
2. Post/Page Admin interface when older Revision is loaded with notes.

== Version Histroy == 
<p>
1.0 - 2008-03-16: Initial release<br />
1.1 - 2008-03-21: Fixed slight error in INSERT SQL for the new Revision. Also fixed escape on inserted content.<br />
1.1b- 2008-04-04: Changed the 'post_status' value on the saved revision to 'inherit' as this was causing problems with some other plugins. <br />
1.1c- 2008-04-17: Added notation to indication current version.<br />
1.2 - 2008-04-18: Changes to Admin display section to make things work under WP 2.5<br />
1.8 - 2008-05-04: changes to enhance WP 2.5 design integration. Added ability to delete revisions, set a no revision 'minor-edit' option, set a no revisions for life checkbox. Display on last 5 revisions with link to reveal more. <br />
1.8.3 - 2008-05-23: Minor changes to display. Added link to reload most current version of content. <br />
</p>