# DiscourseIntegration (BETA)

Some parts of the implementation were inspired by the [utdrwiki/discussion](https://github.com/utdrwiki/discussion) extension.

This extension is in BETA. It has been tested on MediaWiki 1.46 and Discourse Latest. Best results on [Citizen](https://www.mediawiki.org/wiki/Skin:Citizen), Vector22 tested and working.

Replaces the Discussion/Talk Page button with a link to your Discourse forum and adds up to 3 related posts from your Discourse forum to the bottom of the page like the RelatedArticles extension. Currently only available in English.

## Requirements

* MediaWiki 1.43.0+ (1.46 tested)
* Discourse Latest

## Suggestions

* [Citizen](https://www.mediawiki.org/wiki/Skin:Citizen) skin (recommended)
* [RelatedArticles](https://www.mediawiki.org/wiki/Extension:RelatedArticles) extension (recommended) (extension not tested without RelatedArticles, use for best stability)

## Installation

1. Have a working Discourse instance set up with admin access
2. Download and place the extension in your `mediawiki/extensions` directory
3. Run `sudo chmod -R 755 extensions/DiscourseIntegration` if necessary
4. Add `wfLoadExtension( 'DiscourseIntegration' );` to your `LocalSettings.php`
5. Add the required configuration options to your `LocalSettings.php`, see Config Reference below

## Config Reference

* `$wgDiscourseReplaceTalkPages` **REQUIRED** - default TRUE - Whether to replace Talk Page links with links to Discourse search (e.g., https://discourse.example.com/search?q=Page+Title)
* `$wgDiscourseBaseURL` **REQUIRED** - default "https://discourse.example.com" - Base URL of the Discourse instance. Do not include trailing slash.
* `$wgDiscourseAPIKey` **REQUIRED** - default "" - API key for the Discourse instance. Must be global with read/write permissions.
* `$wgDiscourseAPIUsername` **REQUIRED** - default "system" - Username used to process all API requests.
* `$wgDiscourseTargetNamespaces` **BEST PRACTICE** - default [0] - Namespaces to replace talk page buttons with Discourse threads and show related posts.
* `$wgDiscourseTargetSkins` **BEST PRACTICE** - default ["citizen","vector","vector-2022"] - Skins (in lowercase) to replace talk page buttons with Discourse threads and show related posts.
* `$wgDiscourseExcludeStrings` - default [] - List of strings. If a page title contains any of these, the talk page link will NOT be replaced.
* `$wgDiscourseSquarePFPsForAll` - default FALSE - Use square profile pictures instead of rounded.
* `$wgDiscourseSquarePFPsForUsersWithTitles` - default [] - Use square profile pictures instead of rounded for users with certain titles.
* `$wgDiscourseUseNoFollowOnForumLinks` - default FALSE - Add nofollow to all links to the forum. Not recommended for SEO.
* `$wgDiscourseOpenForumLinksInNewTab` - default TRUE - Open all links to the forum in a new tab using `_blank`, otherwise uses the default behavior of the browser.