<?php

namespace MediaWiki\Extension\DiscourseIntegration;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use WANObjectCache;

class RelatedPosts {
	public const SERVICE_NAME = 'DiscourseIntegrationRelatedPosts';

	public function __construct(
		private readonly ExtensionConfig $config,
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly WANObjectCache $cache,
		private readonly LoggerInterface $logger
	) {
	}
    
	public static function onSkinAfterContentStatic( &$data, $skin ) {
		MediaWikiServices::getInstance()->getService( self::SERVICE_NAME )
			->onSkinAfterContent( $data, $skin );
	}

	public function onSkinAfterContent( &$data, $skin ) {
		$targetNamespaces = $this->config->getTargetNamespaces();
		if ( !in_array( $skin->getTitle()->getNamespace(), $targetNamespaces ) ) {
			return;
		}
        
		if ( !in_array( strtolower( $skin->getSkinName() ), $this->config->getTargetSkins() ) ) {
			return;
		}

        if ( !$skin->getTitle()->exists() ) {
            return;
        }

		$term = $skin->getTitle()->getText();
		$posts = $this->getRelatedPosts( $term );

		if ( empty( $posts ) ) {
			return;
		}

		$html = $this->render( $posts );
		$data .= $html;
	}

	private function render( array $results ): string {
		if ( empty( $results ) ) {
			return '';
		}
		
		$baseUrl = rtrim( $this->config->getBaseUrl(), '/' );

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
            
            $name = $post['name'] ?? $topic['posters'][0]['user']['name'] ?? '';
            $username = $post['username'] ?? $topic['posters'][0]['user']['username'] ?? '';
            $avatarTemplate = $post['avatar_template'] ?? ''; 
            
            $dateStr = $post['created_at'] ?? $topic['created_at'] ?? 'now';
            $date = date( 'M j, Y', strtotime( $dateStr ) );
            
            $blurb = htmlspecialchars( $post['blurb'] ?? '' );
            if ( strlen( $blurb ) > 100 ) {
                $blurb = substr( $blurb, 0, 100 ) . '...';
            }

            $pfpBorderRadius = ($this->config->getSquarePFPForAll() || in_array($post['user_title'], $this->config->getSquarePFPForUsersWithTitles())) ? '10%' : '50%';
            $rel = $this->config->getUseNoFollowOnForumLinks() ? 'nofollow' : '';
            $target = $this->config->getOpenForumLinksInNewTab() ? 'target="_blank"' : '';

            // Tags
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
                    $tagLinks[] = "<a href=\"$tagUrl\" style=\"position: relative; z-index: 2; text-decoration: none; color: var(--color-primary, #36c); background: var(--background-color-interactive-subtle, #f8f9fa); padding: 2px 8px; border-radius: var(--border-radius-medium, 4px); font-size: 0.75rem; font-weight: 500;\">$tagSafe</a>";
                }
                if ( !empty( $tagLinks ) ) {
                    $tagsHtml = '<div style="margin-top: 8px; gap: 6px; display: flex; flex-wrap: wrap;">' . implode( '', $tagLinks ) . '</div>';
                }
            }

            $views = $topic['views'] ?? 0;
            $likes = $topic['like_count'] ?? 0;
            $replies = $topic['posts_count'] ?? 0;
            
            $format = function($n) {
                if ($n >= 1000) return round($n/1000, 1) . 'k';
                return $n;
            };

            $viewsStr = $format($views);
            $likesStr = $format($likes);
            $repliesStr = $format($replies);

            $statsHtml = <<<HTML
            <div style="display: flex; gap: 16px; margin-top: 10px; font-size: 0.75rem; color: var(--color-subtle, #72777d); align-items: center;">
                <div style="display: flex; align-items: center; gap: 4px;" title="$views views">
                    <svg xmlns="http://www.w3.org/2000/svg" height="14" viewBox="0 -960 960 960" width="14" fill="currentColor" style="opacity: 0.8;"><path d="M607.5-372.5Q660-425 660-500t-52.5-127.5Q555-680 480-680t-127.5 52.5Q300-575 300-500t52.5 127.5Q405-320 480-320t127.5-52.5Zm-204-51Q372-455 372-500t31.5-76.5Q435-608 480-608t76.5 31.5Q588-545 588-500t-31.5 76.5Q525-392 480-392t-76.5-31.5ZM214-281.5Q94-363 40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200q-146 0-266-81.5Z"/></svg>
                    <span>$viewsStr</span>
                </div>
                <div style="display: flex; align-items: center; gap: 4px;" title="$likes likes">
                    <svg xmlns="http://www.w3.org/2000/svg" height="14" viewBox="0 -960 960 960" width="14" fill="currentColor" style="opacity: 0.8;"><path d="m480-120-58-52q-101-91-167-157T150-447.5Q111-500 95.5-544T80-634q0-94 63-157t157-63q52 0 99 22t81 62q34-40 81-62t99-22q94 0 157 63t63 157q0 46-15.5 90T810-447.5Q771-395 705-329T538-172l-58 52Z"/></svg>
                     <span>$likesStr</span>
                </div>
                 <div style="display: flex; align-items: center; gap: 4px;" title="$replies replies">
                    <svg xmlns="http://www.w3.org/2000/svg" height="14" viewBox="0 -960 960 960" width="14" fill="currentColor" style="opacity: 0.8;"><path d="M80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Z"/></svg>
                     <span>$repliesStr</span>
                 </div>
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
<img src="$avatarUrl" alt="" style="width: 100%; height: 100%; object-fit: cover;">
HTML;
            }

            // meta
            $meta = [];
            if ( $name ) { $meta[] = htmlspecialchars( $name ) . ' <span style="opacity: 0.6;">@' . htmlspecialchars( $username ) . '</span>'; }
            else if ( $username ) { $meta[] = '@' . htmlspecialchars( $username ); };
            $meta[] = $date;
            $metaStr = implode( '<br />', $meta );

			$listItems .= <<<HTML
<li title="$title" style="position: relative; list-style: none;">
    <a href="$url" rel="$rel noopener noreferrer" $target style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;" aria-label="$title"></a>
	<div class="cdx-card" style="height: 100%; display: flex; padding: 12px; box-sizing: border-box; border-radius: var(--border-radius-medium, 4px);">
        <div style="border-radius: $pfpBorderRadius; overflow: hidden; height: 56px; width: 56px; min-width: 56px; margin-right: 16px; align-self: start; flex-shrink: 0; background-color: var(--background-color-interactive-subtle, #eee);">
            $thumbnailHtml
        </div>
		<div class="cdx-card__text" style="display: flex; flex-direction: column; width: 100%;">
			<span class="cdx-card__text__title" style="text-decoration: none; color: inherit; font-weight: 600; line-height: 1.3; font-size: 1rem; display: block; margin-bottom: 4px;">
                <span class="cdx-card__text__title__content">$title</span>
            </span>
            <div style="font-size: 0.75rem; color: var(--color-subtle, #72777d); margin-bottom: 6px;">$metaStr</div>
            <div style="font-size: 0.875rem; color: var(--color-base, #202122); line-height: 1.4; margin-bottom: 8px;">"$blurb"</div>
            $tagsHtml
            $statsHtml
		</div>
	</div>
</li>
HTML;
		}

		$heading = wfMessage( 'discourseintegration-related-posts' )->escaped();

		return <<<HTML
<aside class="noprint" style="max-width: var(--width-page, 100%); margin: 2em auto; padding-inline: var(--padding-page);">
	<h2 class="read-more-container-heading" style="margin-bottom: 16px;">$heading</h2>
	<ul style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; list-style: none; margin: 0; padding: 0;">
		$listItems
	</ul>
</aside>
HTML;
	}

	private function getRelatedPosts( string $term ): array {
		$key = $this->cache->makeKey( 'discourse-related-posts', md5( $term ), 'v16' );

		return $this->cache->getWithSetCallback(
			$key,
			WANObjectCache::TTL_HOUR,
			function () use ( $term ) {
				$baseUrl = rtrim( $this->config->getBaseUrl(), '/' );
				$apiKey = $this->config->getApiKey();
				$apiUser = $this->config->getApiUsername();

				$searchUrl = "$baseUrl/search.json?q=" . urlencode( $term );
				$req = $this->httpRequestFactory->create( $searchUrl, [
					'method' => 'GET',
					'headers' => [
						'Api-Key' => $apiKey,
						'Api-Username' => $apiUser,
					]
				] );

				$status = $req->execute();
				if ( !$status->isOK() ) {
					return [];
				}

				$data = json_decode( $req->getContent(), true );
				$posts = $data['posts'] ?? [];
                $topics = $data['topics'] ?? [];
                $topicsMap = array_column( $topics, null, 'id' );
                
                // take top 3 unique
                $seenTopics = [];
                $candidates = [];
                foreach ( $posts as $p ) {
                    $tid = $p['topic_id'];
                    if ( !isset( $seenTopics[$tid] ) ) {
                        $seenTopics[$tid] = true;
                        $candidates[] = $p;
                        if ( count( $candidates ) >= 3 ) break;
                    }
                }

                // get full topic/post info from the API for every selected topic
                $results = [];
                foreach ( $candidates as $candidate ) {
                    $slug = $candidate['topic_slug'] ?? 'topic';
                    $id = $candidate['topic_id'];
                    $topicUrl = "$baseUrl/t/$slug/$id.json";
                    
                    $success = false;

                    $topicReq = $this->httpRequestFactory->create( $topicUrl, [
                        'method' => 'GET',
                        'headers' => [
                            'Api-Key' => $apiKey,
                            'Api-Username' => $apiUser,
                        ]
                    ] );

                    $topicStatus = $topicReq->execute();
                    if ( $topicStatus->isOK() ) {
                        $topicData = json_decode( $topicReq->getContent(), true );
                        
                        if ( is_array( $topicData ) && isset( $topicData['post_stream']['posts'][0] ) ) {
                            // post_stream.posts[0] is the OP
                            $firstPost = $topicData['post_stream']['posts'][0];

                            $cooked = $firstPost['cooked'] ?? '';
                            $blurb = trim( html_entity_decode( strip_tags( $cooked ) ) );
                            $blurb = preg_replace( '/\s+/', ' ', $blurb );
                            $firstPost['blurb'] = $blurb;
                            
                            // tags
                            $searchTopicData = $topicsMap[$id] ?? [];
                            $tags = $searchTopicData['tags'] ?? [];
                            
                            $results[] = [
                                'topic' => $topicData, 
                                'post' => $firstPost,
                                'tags' => $tags
                            ];
                            $success = true;
                        }
                    } 
                    
                    if ( !$success ) {
                        // fallback to searching data
                        $topic = $topicsMap[$id] ?? null;
                        if ( $topic ) {
                            $results[] = [
                                'topic' => $topic, 
                                'post' => $candidate,
                                'tags' => $topic['tags'] ?? []
                            ];
                        }
                    }
                }
				
				return $results;
			}
		);
	}
}

