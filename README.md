# DiscourseIntegration (BETA)

Some parts of the implementation were inspired by the [utdrwiki/discussion](https://github.com/utdrwiki/discussion) extension.

This extension is in BETA. It has been tested on MediaWiki 1.46 and Discourse Latest. Best results on [Citizen](https://www.mediawiki.org/wiki/Skin:Citizen), Vector22 tested and working.

Replaces the Discussion/Talk Page button with a link to your Discourse forum and adds up to 3 related posts from your Discourse forum to the bottom of the page like the RelatedArticles extension. Currently only available in English. This extension does not act as a bridge between MediaWiki SSO and Discourse SSO. Depending on your Discourse instance's configuration, you may be able to use dummy variables for the API key and username.

## Requirements

* MediaWiki 1.43.0+ (1.46 verified)
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

* `$wgDiscourseReplaceTalkPages` **REQUIRED** - default `TRUE` - Whether to replace Talk Page links with links to Discourse search (e.g., https://discourse.example.com/search?q=Page+Title)
* `$wgDiscourseBaseURL` **REQUIRED** - default `"https://discourse.example.com"` - Base URL of the Discourse instance. Do not include trailing slash.
* `$wgDiscourseAPIKey` **REQUIRED** - default `""` - API key for the Discourse instance. Must be global with read/write permissions.
* `$wgDiscourseAPIUsername` **REQUIRED** - default `"system"` - Username used to process all API requests.
* `$wgDiscourseTargetNamespaces` **BEST PRACTICE** - default `[0]` - Namespaces to replace talk page buttons with Discourse threads and show related posts.
* `$wgDiscourseTargetSkins` **BEST PRACTICE** - default `["citizen","vector","vector-2022"]` - Skins (in lowercase) to replace talk page buttons with Discourse threads and show related posts.
* `$wgDiscourseExcludeStrings` - default `[]` - List of strings. If a page title contains any of these, the talk page link will NOT be replaced.
* `$wgDiscourseExcludePages` - default `["Main_Page", "Home", "Main", "Mainpage"]` - List of page titles. If a page title matches any of these, the talk page link will NOT be replaced and related posts will not be shown.
* `$wgDiscourseSquarePFPsForAll` - default `FALSE` - Use square profile pictures instead of rounded.
* `$wgDiscourseSquarePFPsForUsersWithTitles` - default `[]` - Use square profile pictures instead of rounded for users with certain titles.
* `$wgDiscourseUseNoFollowOnForumLinks` - default `FALSE` - Add nofollow to all links to the forum. Not recommended for SEO.
* `$wgDiscourseOpenForumLinksInNewTab` - default `TRUE` - Open all links to the forum in a new tab using `_blank`, otherwise uses the default behavior of the browser.
* `$wgDiscourseCacheTTL` - default `3600` - How long (in seconds) to cache Discourse API responses. Failed API calls are cached for 5 minutes to avoid hammering Discourse.
* `$wgDiscourseTopicSortOrder` - default `""` - Sort order for related topics. When empty, searches by relevance. Accepts values like `"latest"`, `"likes"`, `"views"`.

## Changelog

### 0.2.0

* **Caching improvements**: Configurable cache TTL via `$wgDiscourseCacheTTL`, negative caching (failed API responses are cached for 5 minutes), and HTTP request timeouts
* **Bug fix**: `DiscourseExcludePages` was missing from `CONSTRUCTOR_OPTIONS` and could cause assertion failures
* **Proper hook handler DI**: `RelatedPosts` is now registered as a `HookHandler` via `extension.json` with dependency injection instead of using a static bridge method
* **CSS refinement**: All inline styles moved to `resources/styles.css` — styles are now skin-overridable and browser-cacheable
* **Styles module loading**: The `ext.DiscourseIntegration.styles` module is now actually loaded (was previously defined but never added to the page output)
* **Error logging**: API failures are now logged via MediaWiki's logging framework (`DiscourseIntegration` channel)

## TODO

* Potentially delay paint until user scrolls to the bottom like RelatedArticles