<?php

namespace MediaWiki\Extension\DiscourseIntegration;

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use WANObjectCache;

class RelatedPosts {
	public const SERVICE_NAME = 'DiscourseIntegrationRelatedPosts';

	/** Cache version — bump when output format changes. */
	private const CACHE_VERSION = 4;

	/** How long to cache an empty/error result so we don't hammer Discourse. */
	private const NEGATIVE_CACHE_TTL = 300; // 5 minutes

	private readonly LoggerInterface $logger;

	public function __construct(
		private readonly ExtensionConfig $config,
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly WANObjectCache $cache,
	) {
		$this->logger = LoggerFactory::getInstance( 'DiscourseIntegration' );
	}

	/**
	 * Hook handler — registered via HookHandlers in extension.json.
	 * @param string &$data
	 * @param \Skin $skin
	 */
	public function onSkinAfterContent( &$data, $skin ) {
		// only show related posts when reading
		if ( $skin->getRequest()->getVal( 'action', 'view' ) !== 'view' ) {
			return;
		}

		if ( !$this->config->getShowRelatedPosts() ) {
			return;
		}

		$postsNamespaces = $this->config->getPostsNamespaces();
		if ( !in_array( $skin->getTitle()->getNamespace(), $postsNamespaces ) ) {
			return;
		}
        
		if ( !in_array( strtolower( $skin->getSkinName() ), $this->config->getPostsSkins() ) ) {
			return;
		}

        $title = $skin->getTitle();

        if ( !$title->exists() ) {
            return;
        }

        $excludePages = $this->config->getExcludePages();
		if ( in_array( $title->getPrefixedText(), $excludePages ) ) {
			return;
		}

		$term = $title->getText();
		$posts = $this->getRelatedPosts( $term );

		if ( empty( $posts ) ) {
			return;
		}

		$skin->getOutput()->addModuleStyles( [ 'ext.DiscourseIntegration.styles' ] );
		$html = $this->render( $posts );
		$data .= $html;
	}

	private function render( array $results ): string {
		if ( empty( $results ) ) {
			return '';
		}
		
		$baseUrl = rtrim( $this->config->getBaseUrl(), '/' );
		$siteName = htmlspecialchars( $this->config->getSiteName() );
		$isSquarePFPForAll = $this->config->getSquarePFPForAll();
		$squarePFPUsers = $this->config->getSquarePFPForUsersWithTitles();

		$listItems = '';
		foreach ( $results as $item ) {
            $topic = $item['topic'];
            $post = $item['post'];

            $tags = $item['tags'] ?? [];
            if ( empty( $tags ) ) {
                $tags = $topic['tags'] ?? [];
            }

            // get data
			$title = htmlspecialchars( $topic['title'] ?? $post['topic_title'] ?? '' );
            $slug = $topic['slug'] ?? $post['topic_slug'] ?? 'topic';
            $id = $topic['id'] ?? $post['topic_id'];
			$url = htmlspecialchars( "$baseUrl/t/$slug/$id" );
            
            $name = $post['name'] ?? $topic['details']['created_by']['name'] ?? $topic['posters'][0]['user']['name'] ?? '';
            $username = $post['username'] ?? $topic['details']['created_by']['username'] ?? $topic['posters'][0]['user']['username'] ?? '';
            $avatarTemplate = $post['avatar_template'] ?? $topic['details']['created_by']['avatar_template'] ?? ''; 
            
            $dateStr = $post['created_at'] ?? $topic['created_at'] ?? 'now';
            $date = date( 'M j, Y', strtotime( $dateStr ) );
            
            $blurbText = $post['blurb'] ?? '';
            $blurbHtml = nl2br( htmlspecialchars( $blurbText ) );

            $pfpClass = ($isSquarePFPForAll || in_array(($post['user_title'] ?? ''), $squarePFPUsers)) ? 'discourse-pfp-square' : 'discourse-pfp-circle';
            $rel = $this->config->getUseNoFollowOnForumLinks() ? 'nofollow' : '';
            $target = $this->config->getOpenForumLinksInNewTab() ? 'target="_blank"' : '';

            // tags
            $tagsHtml = '';
            if ( !empty( $tags ) ) {
                $tagLinks = [];
                foreach ( array_slice( $tags, 0, 3 ) as $tag ) {
                    $tagName = '';
                    if ( is_array( $tag ) ) {
                        $tagName = $tag['name'] ?? $tag['slug'] ?? '';
                    } elseif ( is_string( $tag ) ) {
                        $tagName = $tag;
                    }
                    
                    if ( empty( $tagName ) ) {
                        continue;
                    }
                    
                    $tagUrl = htmlspecialchars( "$baseUrl/tag/$tagName" );
                    $tagSafe = htmlspecialchars( $tagName );
                    $tagLinks[] = "<a href=\"$tagUrl\" class=\"discourse-tag\">$tagSafe</a>";
                }
                if ( !empty( $tagLinks ) ) {
                    $tagsHtml = '<div class="discourse-tags-container">' . implode( '', $tagLinks ) . '</div>';
                }
            }

            $views = $topic['views'] ?? 0;
            $likes = $topic['like_count'] ?? 0;
            $replies = max(0, ($topic['reply_count'] ?? $topic['posts_count'] ?? 1) - 1);
            
            $format = function($n) {
                if ($n >= 1000) return round($n/1000, 1) . 'k';
                return $n;
            };

            $viewsStr = $format($views);
            $likesStr = $format($likes);
            $repliesStr = $format($replies);

            $postersHtml = '';
            $posters = $topic['details']['participants'] ?? $topic['posters'] ?? [];
            if ( !empty( $posters ) ) {
                $posterAvatars = [];
                $maxPosters = 3;
                $posterCount = 0;
                foreach ( $posters as $poster ) {
                    if ( $posterCount >= $maxPosters ) break;
                    $pUser = $poster['user'] ?? $poster ?? null;
                    if ( $pUser && !empty( $pUser['avatar_template'] ) ) {
                        $pAvatarPath = str_replace( '{size}', '40', $pUser['avatar_template'] );
                        if ( !str_starts_with( $pAvatarPath, 'http' ) ) {
                            $pAvatarPath = $baseUrl . (str_starts_with($pAvatarPath, '/') ? '' : '/') . $pAvatarPath;
                        }
                        $pAvatarUrl = htmlspecialchars( $pAvatarPath );
                        $pUsernameSafe = htmlspecialchars( $pUser['username'] ?? '' );
                        $zIndex = 10 - $posterCount;
                        $posterAvatars[] = <<<HTML
<img src="$pAvatarUrl" title="$pUsernameSafe" alt="$pUsernameSafe" class="discourse-poster-avatar" style="z-index: $zIndex;">
HTML;
                        $posterCount++;
                    }
                }
                if ( !empty( $posterAvatars ) ) {
                    $postersHtml = '<div class="discourse-posters-container">' . implode( '', $posterAvatars ) . '</div>';
                }
            }

            $statsHtml = <<<HTML
            <div class="discourse-stats-container">
                <div class="discourse-stats-left">
                    <div class="discourse-stat-item" title="$views views">
                        <svg class="discourse-stat-icon" xmlns="http://www.w3.org/2000/svg" height="14" viewBox="0 -960 960 960" width="14" fill="currentColor"><path d="M607.5-372.5Q660-425 660-500t-52.5-127.5Q555-680 480-680t-127.5 52.5Q300-575 300-500t52.5 127.5Q405-320 480-320t127.5-52.5Zm-204-51Q372-455 372-500t31.5-76.5Q435-608 480-608t76.5 31.5Q588-545 588-500t-31.5 76.5Q525-392 480-392t-76.5-31.5ZM214-281.5Q94-363 40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200q-146 0-266-81.5Z"/></svg>
                        <span>$viewsStr</span>
                    </div>
                    <div class="discourse-stat-item" title="$likes likes">
                        <svg class="discourse-stat-icon" xmlns="http://www.w3.org/2000/svg" height="14" viewBox="0 -960 960 960" width="14" fill="currentColor"><path d="m480-120-58-52q-101-91-167-157T150-447.5Q111-500 95.5-544T80-634q0-94 63-157t157-63q52 0 99 22t81 62q34-40 81-62t99-22q94 0 157 63t63 157q0 46-15.5 90T810-447.5Q771-395 705-329T538-172l-58 52Z"/></svg>
                         <span>$likesStr</span>
                    </div>
                     <div class="discourse-stat-item" title="$replies replies">
                        <svg class="discourse-stat-icon" xmlns="http://www.w3.org/2000/svg" height="14" viewBox="0 -960 960 960" width="14" fill="currentColor"><path d="M80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Z"/></svg>
                         <span>$repliesStr</span>
                     </div>
                </div>
                $postersHtml
            </div>
HTML;

            // avatar
            $thumbnailHtml = '';
            if ( !empty( $avatarTemplate ) ) {
                $avatarPath = str_replace( '{size}', '64', $avatarTemplate );
                if ( !str_starts_with( $avatarPath, 'http' ) ) {
                    $avatarPath = $baseUrl . $avatarPath;
                }
                $avatarUrl = htmlspecialchars( $avatarPath );
                $thumbnailHtml = <<<HTML
<img src="$avatarUrl" alt="" class="discourse-avatar-img">
HTML;
            }

            // try to use extracted preview image, else nothing
            $previewImageUrl = $post['preview_image'] ?? $topic['image_url'] ?? '';
            $previewImageHtml = '';
            if ( $previewImageUrl ) {
                $previewImageUrlSafe = htmlspecialchars( $previewImageUrl );
                $previewImageHtml = <<<HTML
<div class="discourse-related-card-image-wrapper">
    <img src="$previewImageUrlSafe" alt="" class="discourse-preview-img">
    <div class="discourse-related-card-overlay">
        <span class="discourse-overlay-content">
            View on $siteName
            <svg xmlns="http://www.w3.org/2000/svg" height="15" viewBox="0 -960 960 960" width="15" fill="currentColor"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h560v-280h80v280q0 33-23.5 56.5T760-120H200Zm188-212-56-56 372-372H560v-80h280v280h-80v-144L388-332Z"/></svg>
        </span>
    </div>
</div>
HTML;
            }

            // meta
            $meta = [];
            if ( $name ) { $meta[] = htmlspecialchars( $name ) . ' <span class="discourse-meta-username">@' . htmlspecialchars( $username ) . '</span>'; }
            else if ( $username ) { $meta[] = '@' . htmlspecialchars( $username ); };
            $meta[] = $date;
            $metaStr = implode( ' &bull; ', $meta );

			$listItems .= <<<HTML
<li title="$title" class="discourse-card-wrapper">
    <a href="$url" rel="$rel noopener noreferrer" $target class="discourse-card-link" aria-label="$title"></a>
	<div class="cdx-card discourse-card">
        $previewImageHtml
        <div class="discourse-card-body">
            <div class="discourse-card-pfp-wrapper $pfpClass">
                $thumbnailHtml
            </div>
            <div class="cdx-card__text discourse-card-text-wrapper">
                <span class="cdx-card__text__title discourse-card-title">
                    <span class="cdx-card__text__title__content">$title</span>
                </span>
                <div class="discourse-card-meta-new">$metaStr</div>
                <div class="discourse-card-blurb">
                    $blurbHtml
                </div>
                $tagsHtml
                <div class="discourse-card-footer">
                    $statsHtml
                </div>
            </div>
        </div>
	</div>
</li>
HTML;
		}

		$heading = wfMessage( 'discourseintegration-related-posts' )->escaped();

		return <<<HTML
<aside class="discourse-related-posts">
	<div class="discourse-related-posts-container">
        <div class="noprint">
            <h2 class="read-more-container-heading discourse-related-heading">$heading</h2>
            <ul class="discourse-card-list">
                $listItems
            </ul>
        </div>  
    </div>
</aside>
HTML;
	}

	/**
	 * Fetch related posts from Discourse with caching.
	 *
	 * Uses WANObjectCache with configurable TTL. On API failure, caches
	 * empty results for 5 minutes (negative caching) to avoid hammering
	 * the Discourse instance.
	 */
	private function getRelatedPosts( string $term ): array {
		$ttl = $this->config->getCacheTTL();
		$key = $this->cache->makeKey( 'discourse-related-posts', md5( $term ), 'v' . self::CACHE_VERSION );

		return $this->cache->getWithSetCallback(
			$key,
			$ttl,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $term ) {
				$baseUrl = rtrim( $this->config->getBaseUrl(), '/' );
				$apiKey = $this->config->getApiKey();
				$apiUser = $this->config->getApiUsername();

				// --- Step 1: Search Discourse ---
				$searchQuery = $term;
				$sortOrder = $this->config->getTopicSortOrder();
				if ( $sortOrder !== '' ) {
					$searchQuery .= ' order:' . $sortOrder;
				}
				$searchUrl = "$baseUrl/search.json?q=" . urlencode( $searchQuery );
				$req = $this->httpRequestFactory->create( $searchUrl, [
					'method' => 'GET',
					'timeout' => 10,
					'headers' => [
						'Api-Key' => $apiKey,
						'Api-Username' => $apiUser,
					]
				] );

				$status = $req->execute();
				if ( !$status->isOK() ) {
					$this->logger->warning(
						'Discourse search API request failed for term "{term}": {error}',
						[ 'term' => $term, 'error' => $status->getMessage()->text() ]
					);
					$ttl = self::NEGATIVE_CACHE_TTL;
					return [];
				}

				$data = json_decode( $req->getContent(), true );
				if ( !is_array( $data ) ) {
					$this->logger->warning(
						'Discourse search API returned invalid JSON for term "{term}"',
						[ 'term' => $term ]
					);
					$ttl = self::NEGATIVE_CACHE_TTL;
					return [];
				}

				$posts = $data['posts'] ?? [];
                $topics = $data['topics'] ?? [];
                $topicsMap = array_column( $topics, null, 'id' );
                
                // take top N unique topics, skipping system reply posts
                // post_number 1 = OP, post_number > 1 = reply
                $maxPosts = $this->config->getMaxRelatedPosts();
                $seenTopics = [];
                $candidates = [];
                foreach ( $posts as $p ) {
                    $pUsername = $p['username'] ?? '';
                    $pNumber = $p['post_number'] ?? 1;
                    if ( $pUsername === 'system' && $pNumber > 1 ) {
                        continue;
                    }
                    $tid = $p['topic_id'];
                    if ( !isset( $seenTopics[$tid] ) ) {
                        $seenTopics[$tid] = true;
                        $candidates[] = $p;
                        if ( count( $candidates ) >= $maxPosts ) break;
                    }
                }

                // if all results were system posts, retry without the filter
                if ( empty( $candidates ) ) {
                    $seenTopics = [];
                    foreach ( $posts as $p ) {
                        $tid = $p['topic_id'];
                        if ( !isset( $seenTopics[$tid] ) ) {
                            $seenTopics[$tid] = true;
                            $candidates[] = $p;
                            if ( count( $candidates ) >= $maxPosts ) break;
                        }
                    }
                }

				if ( empty( $candidates ) ) {
					$ttl = self::NEGATIVE_CACHE_TTL;
					return [];
				}

                // get full topic/post info from the API for every selected topic
                $results = [];
                foreach ( $candidates as $candidate ) {
                    $id = $candidate['topic_id'];
                    $topicUrl = "$baseUrl/t/$id.json";
                    
                    $success = false;

                    $topicReq = $this->httpRequestFactory->create( $topicUrl, [
                        'method' => 'GET',
						'timeout' => 10,
                        'headers' => [
                            'Api-Key' => $apiKey,
                            'Api-Username' => $apiUser,
                        ]
                    ] );

                    $topicStatus = $topicReq->execute();
                    if ( $topicStatus->isOK() ) {
                        $topicData = json_decode( $topicReq->getContent(), true );
                        
                        if ( is_array( $topicData ) && !empty( $topicData['post_stream']['posts'] ) ) {
                            // find the actual first user post: post_number 1 AND post_type 1 (regular)
                            // this avoids system info posts on locked/closed topics
                            $firstPost = null;
                            $firstRegularPost = null;
                            foreach ( $topicData['post_stream']['posts'] as $streamPost ) {
                                $pNum = $streamPost['post_number'] ?? 0;
                                $pType = $streamPost['post_type'] ?? 1;
                                // best match: post #1 that is a regular post
                                if ( $pNum === 1 && $pType === 1 ) {
                                    $firstPost = $streamPost;
                                    break;
                                }
                                // track first regular post as a secondary fallback
                                if ( $firstRegularPost === null && $pType === 1 ) {
                                    $firstRegularPost = $streamPost;
                                }
                            }
                            // fallback chain: post_number 1 (any type) → first regular post → first post in stream
                            if ( $firstPost === null ) {
                                // try post_number 1 even if it's not type 1
                                foreach ( $topicData['post_stream']['posts'] as $streamPost ) {
                                    if ( ( $streamPost['post_number'] ?? 0 ) === 1 ) {
                                        $firstPost = $streamPost;
                                        break;
                                    }
                                }
                            }
                            if ( $firstPost === null ) {
                                $firstPost = $firstRegularPost ?? $topicData['post_stream']['posts'][0];
                            }

                            $cooked = $firstPost['cooked'] ?? '';
                            
                            // safely parse HTML to remove quotes and oneboxes properly
                            $dom = new \DOMDocument();
                            $html = '<?xml encoding="utf-8" ?>' . $cooked;
                            @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                            $xpath = new \DOMXPath($dom);
                            
                            // remove oneboxes, blockquotes, mentions at the node level
                            $nodes = $xpath->query('//aside | //blockquote | //div[contains(@class, "onebox")] | //a[contains(@class, "mention")]');
                            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                                $node = $nodes->item($i);
                                $node->parentNode->removeChild($node);
                            }
                            
                            // ensure spacing for block level elements before extracting text
                            $blocks = $xpath->query('//p | //br | //div | //hr | //li');
                            foreach ($blocks as $node) {
                                $node->appendChild($dom->createTextNode("\n"));
                            }
                            
                            $blurb = trim($dom->textContent);
                            
                            // remove raw urls cleanly (does not eat adjacent words)
                            $blurb = preg_replace( '/(?:https?:\/\/|www\.)[^\s]+/i', '', $blurb );
                            
                            // remove Discourse-specific placeholders and broken syntax
                            $blurb = str_ireplace( '[image]', '', $blurb );
                            $blurb = str_ireplace( '[media]', '', $blurb );
                            $blurb = preg_replace( '/\[\/?(?:quote|code|spoiler|md|html).*?\]/is', '', $blurb );
                            
                            // remove md links and images syntax
                            $blurb = preg_replace( '/!?\[([^\]]*)\]\([^)]*\)/', '$1', $blurb );

                            $blurb = preg_replace( '/[ \t]+/', ' ', $blurb );
                            $blurb = trim( preg_replace( '/\n\s*\n+/', "\n\n", $blurb ) );
                            
                            if ( mb_strlen( $blurb ) > 250 ) {
                                $blurb = mb_substr( $blurb, 0, 250 ) . '...';
                            }
                            $firstPost['blurb'] = $blurb;
                            
                            // extract image
                            $searchTopicData = $topicsMap[$id] ?? [];
                            $postImageUrl = $topicData['image_url'] ?? $searchTopicData['image_url'] ?? null;
                            if ( !$postImageUrl && !empty( $cooked ) ) {
                                // find all images and pick the first one
                                if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $cooked, $matches, PREG_SET_ORDER ) ) {
                                    foreach ( $matches as $match ) {
                                        $fullImg = $match[0];
                                        $src = $match[1];
                                        if ( !preg_match( '/class=["\'][^"\']*emoji/i', $fullImg ) && !str_contains( $src, '/images/emoji/' ) ) {
                                            if ( !str_starts_with( $src, 'http' ) && !str_starts_with( $src, 'data:' ) ) {
                                                $src = $baseUrl . (str_starts_with($src, '/') ? '' : '/') . $src;
                                            }
                                            $postImageUrl = $src;
                                            break;
                                        }
                                    }
                                }
                            }
                            $firstPost['preview_image'] = $postImageUrl;
                            
                            // tags
                            $tags = $searchTopicData['tags'] ?? [];
                            
                            $results[] = [
                                'topic' => $topicData, 
                                'post' => $firstPost,
                                'tags' => $tags
                            ];
                            $success = true;
                        }
                    } else {
						$this->logger->info(
							'Discourse topic API request failed for topic {id}: {error}',
							[ 'id' => $id, 'error' => $topicStatus->getMessage()->text() ]
						);
					}
                    
                    if ( !$success ) {
                        // fallback to search data, but skip system action posts
                        $cUsername = $candidate['username'] ?? '';
                        $cNumber = $candidate['post_number'] ?? 1;
                        if ( $cUsername === 'system' && $cNumber > 1 ) {
                            continue;
                        }
                        $topic = $topicsMap[$id] ?? null;
                        if ( $topic ) {
                            $results[] = [
                                'topic' => $topic, 
                                'post' => $candidate,
                                'tags' => $topic['tags'] ?? [],
                                'fallback' => true
                            ];
                        }
                    }
                }
				
				return $results;
			}
		);
	}
}
