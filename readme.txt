=== Simple Comment Editing ===
Contributors: ronalfy
Tags: comment editing, comments ,edit comments, ajax comments, comment moderation
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 3.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allow your users to edit their comments for a period of time. Adjust the comment timer and save some admin headaches.

== Description ==

Allow your users to edit their comments and WooCommerce reviews for a period of time.

[youtube https://www.youtube.com/watch?v=bNCDdQbwA-s&rel=0]

<a href="https://github.com/ronalfy/simple-comment-editing-docs">Getting Started</a> | <a href="https://github.com/ronalfy/simple-comment-editing">Simple Comment Editing on GitHub</a>

=== Simple Comment Editing features ===
<ol>
<li>Install the plugin. That's it. It just works.
<li>Anonymous users can edit comments for 5 minutes.</li>
<li>No styling is necessary. For advanced customization, see the "Other Notes" section.</li>
<li>Advanced customization can be achieved using filters.</li>
<li>Add Mailchimp to your comment form and get email subscribers through comments.</li>
</ol>

== Installation ==

1. Just unzip and upload the "simple-comment-editing" folder to your '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Can users edit WooCommerce review ratings? =
Yes. The review editor keeps its rating control scoped to the existing review being edited, while the native new-review form continues to use WooCommerce's own rating control.

= Why are the edit controls missing, hidden, or unable to save? =
First identify what the browser received. Inspect the comment for SCE markup,
then use the browser Network and Console panels to check that
`dist/sce-editing.js` loads as JavaScript after `wp-i18n` and `wp-hooks` (and
`wp-editor` when Quicktags is enabled). Inspect the timer or `admin-ajax.php`
request before changing settings: an expired timer, a nonce/permission failure, or a
server error needs a different fix. Preserve the comment text and do not expose
controls by bypassing SCE's server-side ownership or nonce checks.

= How should I test a cache, CDN, proxy, or migration problem? =
Use a new test comment in a private browser window, then purge the WordPress,
proxy/CDN, and browser caches before every retest. For W3 Total Cache and
similar tools, temporarily exclude `dist/sce-editing.js` and
`dist/integrations-admin.js` from minification, combination, defer/delay, and
re-minification; purge and retest before adding optimizations back one at a
time. Do not share cached authenticated or edit-author pages with other
visitors. For Cloudflare, separately test script optimization, cached HTML,
origin HTTP/HTTPS scheme, and client-IP forwarding; none is a known universal
root cause. Keep security and anti-spam protections enabled and use the
narrowest documented exclusion needed.

After a migration, verify WordPress Address and Site Address, the page and
`admin-ajax.php` origins, and the commenter's cookie domain/path/secure flag.
For an unresolved failure, collect the first console error, asset/AJAX status
and response headers, full server exception/stack trace, versions, and a
standard-theme reproduction. Remove cookies, nonces, secret values, private
comment content, and request bodies from any public report. The GitHub README
has the full cache/proxy/migration diagnostic guide.

= Was this plugin called Comment Edit Core? =
Yes. The plugin has been renamed back to Simple Comment Editing.

= How do translations work? =
Bundled translations provide the default frontend labels and countdown text when no WordPress.org language pack is installed, including German and German Formal. WordPress.org and Loco translations can still override the bundled defaults. Labels customized in Settings are saved site values, so update those values directly when changing site languages.

= Can commenters use an HTML toolbar? =
Yes. Enable Quicktags Toolbar in Settings -> Simple Comment Editing to add WordPress's native Quicktags toolbar to the comment editor. It is disabled by default, does not load TinyMCE or CKEditor, and can be left disabled when another plugin uses the documented JavaScript editor lifecycle.

= Can I add custom fields or use a custom comment loop? =
Yes. The integration guide in the GitHub README documents comment-scoped extra fields, safe metadata saving, custom loops that call WordPress's native `comment_text()` path, button HTML, and inline action-row styling. Keep SCE's editor wrappers and controls intact so its nonce, ownership checks, Save, Cancel, and Delete actions continue to work.

= Can SCE initialize AJAX-submitted or lazy-loaded comments? =
Yes. SCE observes newly inserted controls, and integrations can call `window.SCE.init( insertedComment )` after attaching their markup. Repeated calls reuse handlers and the countdown. The existing WP Ajaxify event and `SCE_comments_updated` callback remain supported. Assets preload on singular content with open or existing comments, including for first-time guests; the asset-loading policies and `sce_load_scripts` override still apply.

Custom AJAX submitters must render through WordPress's native `comment_text()` path and opt into `sce_ajax_comment_submission` only around their existing validated comment-creation call, before sending output. The README has the adapter fragment. This establishes the guest cookie in the original submission response, not through a public comment-ID claim endpoint. Initialization never bypasses nonce, ownership, or expiry checks.

Lazy Load for Comments uses the DOM-insertion contract rather than a private plugin event; this is not a claim that every plugin-version combination has been verified. The BuddyBoss adapter is explicitly unverified and requires both native comment rendering and the validated server submission route. Test actual integrations with separate owner/non-owner browsers before release.

The GitHub README records browser verification with Ajaxify Comments 3.2.2 and Lazy Load for Comments 2.1.0 on Twenty Twenty-Five, including the Ajaxify comments-container selector required by that theme.

= Can moderators move a comment to another post? =
Yes. In Dashboard -> Comments, use a comment's Move action (or Comments -> Move
comment). Enter the destination post ID, choose only that comment or its entire
reply subtree, then preview and explicitly confirm. This is an admin-only tool:
it requires comment moderation, edit access to every affected comment, and read
and edit access to both posts. It does not add public commenter controls.

The moved root becomes top-level on the destination. If replies stay behind,
direct children inherit the root's former parent on the original post (or become
top-level); deeper replies keep their parents. If replies move, their internal
parents are preserved. The preview shows both posts, their statuses, and the
number of comments moving or staying. Moving approved comments can expose them
on the destination; content, approval and metadata are not changed.

The destination must accept comments. Trashed posts, attachments, WooCommerce
products/reviews, pingbacks, trackbacks, spam/trashed comments, invalid threads,
and threads over 500 comments are rejected. Closed source posts are allowed.
InnoDB comment, post and option tables plus database advisory locks and
serializable transactions are required; unsupported storage fails without moving
comments. Placement and both approved-comment counts are updated in one
transaction. A changed preview or a known rolled-back failure can be retried
after previewing again; it does not block later moves.

= How can I recover an interrupted or mistaken move? =
Before any placement changes, SCE saves a non-autoloaded private option named
`_sce_comment_move_<operation UUID>` containing before/after comment post and
parent IDs, the initiating moderator ID, time and outcome. It contains no comment
content or author details. `_sce_comment_move_pending` is an additive, normally
absent pointer to an unresolved operation; no existing option defaults change and
no backfill runs. Records are retained, not automatically expired or removed by
this feature, so an interrupted operation does not lose its recovery data.

The outcome screen provides Restore original placement. An interrupted operation
is also linked from Comments -> Move comment and blocks new moves until recovery.
Recovery has its own nonce and checks current permissions on both posts and all
affected comments. It restores placements and counts only, not content or
metadata. It refuses new replies, unrelated placement changes, missing parents,
or cycles instead of overwriting another moderator's work. Retain the journal
when investigating a refused recovery or database failure; do not delete the
pending marker to bypass it. Normal deactivation does not undo a completed move.

= Why doesn't this plugin come with any styles? =
It's impossible to style an inline comment editor for every theme.  We've included basic HTML markup that is easily stylable to fit your theme.

= Where are the options? =
You can find the options by logging into your Dashboard and going to Settings->Simple Comment Editing.

= Can I limit where SCE frontend assets load? =
Yes. Settings -> Simple Comment Editing includes opt-in policies to load assets on built-in WordPress posts only, or only where a comment flow is available. The posts-only policy excludes pages, WooCommerce products, and custom post types. The comment-availability policy still loads SCE for existing editable comments and supported Ajaxify Comments lazy loading. Both are disabled by default. Developers can use `sce_load_scripts` after these policies: returning `false` suppresses both SCE JavaScript and CSS, while `true` loads SCE on an excluded singular view.

= What browsers have you tested this with? =
Simple Comment Editing will work all the way back to IE10.

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/simple-comment-editing)

== Screenshots ==

1. Edit Button and Timer.
2. Styled Buttons and Compact Timer.
3. Default button theme.
4. Dark button theme.
5. Light button theme.

== Changelog ==

= Unreleased =
* Fix: Preserve WP ULike comment buttons, their vote state, and their top/bottom placement after inline comment saves.
* New: Add a repeat-safe, root-scoped initializer for AJAX/lazy-loaded comment controls and an opt-in guest ownership hook for validated AJAX submissions.
* New: Add moderator-only comment/subtree movement with a native admin preview, explicit confirmation, thread/count preservation, and recovery records.
* New: Add an optional native Quicktags toolbar and lifecycle hooks for external comment editors.
* Docs: Add supported custom-field, custom-loop, button HTML, and inline action-row integration examples.
* Fix: Keep inline comment editing available after failed AJAX requests, prevent duplicate submissions, and use accessible inline edit controls instead of links to the AJAX endpoint.
* Fix: Load bundled frontend translations and add German Formal countdown support.

= 3.4.1 =
* Released 2026-09-21
* Fix: Restore the comment editing interface for pending comments in block themes.

= 3.4.0 =
* Released 2026-09-11 - Recommended update as this release fixes a few security warnings, removes Pro branding, and renames the plugin back to Simple Comment Editing while porting some pro editing features.
* New: Rebranding/reverting name back to Simple Comment Editing.
* Update: Porting some editing-related features from discontinued Pro plugin.
* New: Adding Settings options for comment editing: show or hide the countdown timer, require login to edit, and unlimited editing for logged-in comment authors.
* New: Adding deletion settings: allow users to delete comments, confirm before delete, and trash vs permanent delete for users and moderators.
* New: Adding moderator spam handling behavior (spam vs spam and delete), including in wp-admin when moderating comments.
* New: Adding customizable labels (Click to Edit, Save, Cancel, Delete) and messages (delete confirmation, deleted, deleted error, empty comment).
* Fix: Settings save now merges options so other saved settings (such as Mailchimp) are not wiped accidentally.
* Security: Security and WordPress.org updates: tightening SQL prepares in uninstall and cleanup queries, better option sanitization when reading/saving settings, ensuring passing Plugin Check report, replacing deprecated functions, added license header, and tested up to WordPress 7.1.

= 3.3.1 =
* Released 2026-09-11
* Renaming the plugin back to Simple Comment Editing.
* Updating author, GitHub, and support URLs.
* Removing Fancybox admin theme previews.

= 3.3.0 =
* Released 2026-03-07
* Reorganizing admin level settings. Ensuring plugin works with WordPress 7.0.

= 3.2.0 =
* Released 2025-11-08
* Security fix: Resolving Unauthenticated Sensitive Information Exposure report from Wordfence. Removing uneeded get comment function, which could expose comment information.

= 3.1.0 =
* Released 2025-01-08
* Security fix: Resolving SSRF security vulnerability when retrieving Mailchimp data as reported by Patchstack.

= 3.0.33 =
* Released 2024-12-11
* Fixing remaining translation warnings in WP 6.7.

= 3.0.32 =
* Released 2024-11-28
* Fixing translation debug warning in WP 6.7 for loading translations to early.

= 3.0.31 =
* Released 2024-09-20
* Fixing missing event for when there is no timer.
* Adding max-width to edit textarea for better styling.

= 3.0.30 =
* Released 2024-08-26
* Fixing issue with Server IP and user-agent not allowing for edited comments.
* Fixed issue with edited comments and comment edit markup showing when re-checking a comment.

= 3.0.21 =
* Released 2024-08-26
* Fixing bug with `check_comment` function always returning that a comment should be in moderation when saving an edited comment.

= 3.0.20 =
* Released 2024-06-25
* Fixing Ajax JS error for timers on lazy loaded comments.
* Hiding non-plugin notices on the settings page.

= 3.0.19 =
* Released 2023-12-19
* Compatibility update: Fixing JS events not tied to document and fixing JS filters for other callers.

= 3.0.17 =
* Released 2023-12-08
* Adding synthetic JS event for when the timer has been loaded. This is useful for third-party integrations.

= 3.0.15 =
* Released 2023-10-25
* Adding native Ajaxify Comments integration.

= 3.0.14 =
* Released 2023-09-15
* Fixing fatal error caused by case-sensitive server setups.
* Fixing activation redirect to wrong screen in the admin.

= 3.0.12 =
* Released 2023-08-24
* Fixing fatal error caused by case-sensitive server setups.

= 3.0.11 =
* Released 2023-08-24
* Fixing fatal error caused by case-sensitive server setups.

= 3.0.9 =
* Released 2023-08-24
* Added support for LaTeX when editing comments. You should use a plugin like <a href="https://wordpress.org/plugins/mathjax-latex/">WP LaTeX</a> to render the LaTeX.

= 3.0.5 =
* Released 2023-08-08
* Fixed timer issue not showing minutes correctly with a large timer.

= 3.0.1 =
* Released 2023-07-31
* Resolving fatal error with folder name capitalization.

= 3.0.0 =
* Released 2023-07-31
* Added support for WooCommerce Reviews and editing.
* Re-worked the JavaScript for better extensibility.
* Testing with WordPress 6.3.

= 2.9.7 =
* Released 2023-04-20
* Adding new filter to allow for permanent comment deletion option and override.
* <a href="https://dlxplugins.com/announcements/comment-edit-lite-and-pro-now-support-permanent-comment-deletion/">Read the announcement post</a>.

= 2.9.5 =
* Released 2023-02-24
* Fixing spacing issue with the edit buttons when selecting a button theme.
* New wrapper around the buttons for better styling control.

= 2.9.1 =
* Released 2023-02-17
* Adding new filter for early disabling of comment editing.
* Fixing redirect issue on plugin activation when Comment Edit Pro is installed.

= 2.9.0 =
* Released 2023-02-10
* Rebranding the plugin from Simple Comment Editing to Comment Edit Core.
* <a href="https://dlxplugins.com/announcements/simple-comment-editing-has-been-renamed-to-comment-edit-lite/">Please read more on the rebranding</a> where you can leave questions or comments.

= 2.8.0 =
* Released 2022-10-22
* New: Integrate Mailchimp with your comment section to increase newsletter signups.
* Updating internal documentation.
* Successfully tested with WP 6.1.

= 2.7.4 =
* Released 2022-08-11
* Updating Akismet integration so it can be disabled.

= 2.7.2 =
* Updating logo
* Updating documentation

= 2.7.1 =
* Released 2021-07-02
* Added in a filter for better IP tracking when editing a comment. Props <a href="https://wordpress.org/support/users/tim-reeves/">Tim Reeves</a> for the fix.

= 2.7.0 =
* Released 2021-06-03
* New option: timer can now be compact (e.g., 41:15).
* New option: button themes.
* Using new comment check function name if it exists.
* Correcting typo in admin options.
* Basic styling for edit buttons.

= 2.6.1 =
* Released 2021-06-02
* Fix admin styling.
* Fixing timer for > 5 minutes.

= 2.6.0 =
* Released 2021-05-30
* New admin panel style in anticipation of a few more feature additions.

= 2.5.5 =
* Released 2021-04-25
* Added new filters to allow comment deletion only.

= 2.5.1 =
* Released 2020-04-28
* Fixing undefined variable error.

= 2.5.0 =
* Released 2020-04-26
* Unlimited logged-in comment was always failing.

= 2.4.6 =
* Released 2020-02-17
* Fixing WSOD error with incompatible PHP7 syntax.

= 2.4.5 =
* Released 2020-02-16
* Added better support for multisite.

= 2.4.2 =
* Released 2020-01-13
* Added hook for when the editing dialog is displayed.

= 2.4.1 =
* Released 2019-12-18
* Fix: Users (logged in) can now edit their comments. 
* Fix: Removing ability of authors to see edited-enabled comments.

= 2.4.0 =
* Released 2019-12-13
* Fix: Users could not edit comments.

 = 2.3.14 =
* Released 2019-12-11
* Removing ability of authors to see edited-enabled comments.

= 2.3.12 =
* Released 2019-12-08
* Fixing translation errors for Russian language.

= 2.3.11 =
* Released 2019-12-07
* Internal documentation update.

= 2.3.9 =
* Released 2019-11-06
* Fixed bug where edited (and approved) comments were sent back to moderation.

= 2.3.8 =
* Released 2019-03-17
* Adjusting filters to prevent WSOD

= 2.3.7 =
* Released 2019-03-06
* Added options panel for editing the timer time.

= 2.3.6 =
* Released 2019-02-20
* Added new filter for unlimited editing option.
* Fixing PHP 5.3 fatal error when posting a comment.

= 2.3.5 =
* Released 2019-02-17
* Updating JavaScript hooks to work with WordPress 5.0+

= 2.3.4 =
* Released 2019-02-14
* New hook to allow scripts to load after SCE scripts
* Ability to stop the timer and finish editing

= 2.3.3 =
* Released 2018-11-08
* Fixing timer when it's less than a minute and the timer disaappears

= 2.3.2 =
* Released 2018-11-08
* Added better i18n with JavaScript files. Updated German translation.

= 2.3.1 =
* Released 2018-11-08
* Fixing compatibility with WP Ajaxify Comments

= 2.3.0 =
* Released 2018-11-06
* WordPress 5.0 compatible only
* Enhancement: set the timer past the 90 minute mark
* Enhancement: new filter to hide the timer
* New add-on: <a href="https://mediaron.com/simple-comment-editing-options">Simple Comment Editing Options</a>

= 2.2.1 =
* Released 2018-10-21
* Added CSS around seperator so it can be hidden

= 2.2.0 =
* Released 2018-10-13
* Allow logged in users (author of the post) to bypass the cookies needed for comment editing

= 2.1.11 =
* Released 2018-05-08
* Fixes a bug where a proxy server can give a different IP and prevent the user from editing

= 2.1.9 =
* Released 2018-02-09
* Fixes a bug when the comment is deleted even when canceling the confirmation

= 2.1.7 =
* Released 2017-11-15
* Added filter to remove the delete comment notifications

= 2.1.5 =
* Released 2017-01-20
* Resolving Epoch 1.0 conflict

= 2.1.3 =
* Released 2016-12-07
* Added Thesis compatibility

= 2.1.1 =
* Released 2016-10-18
* Re-added filter `sce_return_comment_text`

= 2.1.0 =
* Released 2016-09-17
* Post meta is no longer used and comment meta is used instead

= 2.0.0 =
* Released 2016-08-14
* Bug fix: Deletion filter now works in JS and in HTML output
* Bug fix: Changing comment time in filter resulted in undefined in JS output
* New filters: Allow changing of edit and save/cancel/delete buttons
* Epoch 2.0 compatible


= 1.9.4 =
* Released 2016-04-02
* Polish translation added

= 1.9.3 =
* Released 2016-03-23
* Fixes issue where Ajax call wouldn't work on non-SSL site but SSL admin
* Resolves double query issue with Epoch
* Resolves comment ghosting with Epoch

= 1.9.1 =
* Released 2015-11-04
* Added minified script for events hooks

= 1.9.0 =
* Released 2015-10-27
* Timer now shows below save/cancel/delete buttons for convenience

= 1.8.5 =
* Released 2015-10-21
* Fixed Portuguese translation (thanks Marco Santos)
* Added Lithuanian translation
* Fixed timer scroll issue where the delay was too long (thanks MamasLT)

= 1.8.3 =
* Released 2015-10-20
* Fixing user logged in issue where unusual timer values are being shown, and the comment appears editable, but is not

= 1.8.1 =
* Released 2015-10-12
* Logged in users who log out can no longer edit comments
* Added Delete button
* Updated translations for language packs

= 1.7.1 =
* Released 2015-09-26
* Fixed Epoch+SCE user logged in dilemma

= 1.7.0 =
* Released 2015-09-20
* Fixed timer issue on many sites. New JS hook for allowing customization of output.

= 1.6.7 =
* Released 2015-09-20
* Fixing PHP bug declaring fatal error for multiple class instances. Props volresource.

= 1.6.5 =
* Released 2015-09-17
* Fixing strings that are not replaced in the timer. Sorry I didn't catch this error.

= 1.6.1 =
* Released 2015-09-16
* Fixed undefined JavaScript errors in timer. Sorry about that.

= 1.6.0 =
* Released 2015-09-16
* Added filter for custom timer output
* Added support for logged in users to bypass cookie checks
* Added support for custom post types

= 1.5.5 =
* Released 2015-09-07
* Fixed return call to be better compatible with third-party customizations
* Added Latvian translation
* Revised WP Ajaxify Comments integration

= 1.5.3 =
* Released 2015-08-23
* Fixing PHP 5.2 error

= 1.5.1 =
* Released 2015-08-19
* Forgot to update minified JS

= 1.5.0 =
* Released 2015-08-19
* Adding hooks for the capability to add extra comment fields.
* Added Epoch compatibility.
* Added JS events so third-party plugins can integrate with SCE.

= 1.3.3 =
* Released 2015-07-22
* Fixing JavaScript error that prevented editing if a certain ID wasn't wrapped around a comment.

= 1.3.2 =
* Released 2015-07-13
* Added filter sce_can_edit for more control over who can or cannot edit a comment.
* Updated translations (Arabic, Dutch, French, German, Norwegian, Persian, Portuguese, Romanian, Russian, Serbian, Spanish, and Swedish).

= 1.3.1 =
* Released 2015-06-26
* Fixed debug error that stated there were two few arguments when there was a percentage sign (%) in a comment. Thank you <a href="https://github.com/ronalfy/simple-comment-editing/issues/7">bernie-simon</a>.

= 1.3.0 =
* Released 2015-06-18
* Improved timer internationalization to accept languages with plurality variations (e.g., Russian)
* Added Russian translation
* Improved the timer to be significantly more accurate
* Added filters to the SCE HTML in order to add custom attributes
* Improved inline documentation
* Added smooth scrolling to the comment after a page load

= 1.2.4 =
* Updated 2015-04-19 - Ensuring WordPress 4.2 compatibility
* Released 2015-02-04
* Added status error message area
* Added filter for custom error messages when saving a comment

= 1.2.2 =
* Updated 2014-12-11 - Ensuring WordPress 4.1 compatibility
* Released 2014-09-02
* Added Romanian language
* Added French language
* Added Dutch language
* Added better support for cached pages
* Fixed a bug where cached pages showed other users they could edit a comment, but in reality, they could not (saving would have failed, so this is not a severe security problem, although upgrading is highly recommended).

= 1.2.1 =
* Released 2014-08-27
* Added Arabic and Czech languages
* Ensuring WordPress 4.0 compatibility

= 1.2.0 =
* Released 2014-05-13
* Added Swedish translation
* Added better support for internationalization
* Removed barrier for admins/editors/authors to edit comments

= 1.1.2 =
* Released 2014-04-14
* Added support for WP-Ajaxify-Comments

= 1.1.1 =
* Released 2014-02-06
* Fixed an error where users were erroneously being told their comment was marked as spam

= 1.1.0 =
* Released 2014-02-05
* Added JavaScript textarea save states when hitting the cancel button
* Allow commenters to delete their comments when they leave an empty comment

= 1.0.7 =
* Released 2013-09-15
* Added Persian translation file

= 1.0.6 =
* Released 2013-09-12
* Added Serbian translation file

= 1.0.5 =
* Released 2013-09-12
* Added Portuguese translation file

= 1.0.4 =
* Released 2013-09-06
* Added German translation file

= 1.0.3 =
* Released 2013-08-23
* Fixed slashes being removed in the plugin

= 1.0.2 =
* Released 2013-08-05
* Fixed an internationalization bug and added Norwegian translations.

= 1.0.1 =
* Released 2013-08-05
* Improved script loading performance

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 3.4.1 =
Restore comment editing for pending comments in block themes.
