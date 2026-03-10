<?php

namespace MediaWiki\Extension\DiscourseIntegration;

// use MediaWiki\MediaWikiServices;
// use MediaWiki\Skin\SkinTemplate;

class Hooks  {

    public function __construct(
		private readonly ExtensionConfig $config,
	) {
	}


	public function onSkinTemplateNavigation__Universal( $skin, &$links ) {
		if ( $this->config->getReplaceTalkPages() == false ) {
			return;
		}

		$title = $skin->getTitle();
		$namespace = $title->getNamespace();
		$skinName = strtolower( $skin->getSkinName() );

		$targetNamespaces = $this->config->getTargetNamespaces();
		$targetSkins = $this->config->getTargetSkins();

		if ( !in_array( $skinName, $targetSkins ) ) {
			return;
		}

		$isTargetNamespace = in_array( $namespace, $targetNamespaces );
		$rootURLForNonMain = $this->config->getRootForumUrlForNonMain();

		// Replace if it's a target namespace, OR if the root toggle is on and it's not main
		if ( !$isTargetNamespace && !( $rootURLForNonMain && $namespace !== 0 ) ) {
			return;
		}

		$excludePages = $this->config->getExcludePages();
		if ( in_array( $title->getPrefixedText(), $excludePages ) ) {
			return;
		}

		$excludeStrings = $this->config->getExcludeStrings();
		$titleText = $title->getPrefixedText();
		foreach ( $excludeStrings as $string ) {
			if ( $string !== '' && strpos( $titleText, $string ) !== false ) {
				return;
			}
		}

		$discourseUrl = $this->config->getBaseUrl();
		if ( !$discourseUrl ) {
			return;
		}
		$discourseUrl = rtrim( $discourseUrl, '/' );

		if ( $rootURLForNonMain && $namespace !== 0 ) {
			$href = $discourseUrl;
		} else {
			$href = $discourseUrl . '/search?q=' . urlencode( $titleText );
		}

		$newLink = [
			'href' => $href,
			'text' => 'Discourse',
			'title' => wfMessage( 'discourseintegration-discourse-button-alt' )->text(),
			'rel' => 'discussion',
			'accesskey' => 't',
			'id' => 'ca-talk',
			'class' => 'discourse-talk-link'
		];
        
		$skin->getOutput()->addModuleStyles( [ 'ext.DiscourseIntegration.styles' ] );

		if ( $namespace === 0 ) {
			foreach ( [ 'namespaces', 'associated-pages' ] as $group ) {
				if ( !isset( $links[$group] ) || !is_array( $links[$group] ) ) {
					continue;
				}
				$foundTalk = false;
				$newLinks = [];
				foreach ( $links[$group] as $key => $link ) {
					if ( $key === 'talk' || $key === 'discussion' || str_ends_with( (string)$key, '_talk' ) ) {
						if ( !$foundTalk ) {
							$newLinks['talk'] = $newLink;
							$foundTalk = true;
						}
					} else {
						$newLinks[$key] = $link;
					}
				}
				$links[$group] = $newLinks;
			}
		} else {
			$found = false;
			foreach ( $links as $group => &$linksInGroup ) {
				if ( !is_array( $linksInGroup ) ) {
					continue;
				}
				foreach ( $linksInGroup as $key => &$link ) {
					if (
						$key === 'talk' ||
						$key === 'discussion' ||
						( isset( $link['rel'] ) && $link['rel'] === 'discussion' ) ||
						( isset( $link['id'] ) && $link['id'] === 'ca-talk' ) ||
						str_ends_with( $key, '_talk' )
					) {
						$link = $newLink;
						$found = true;
					}
				}
			}

			if ( !$found ) {
				if ( isset( $links['associated-pages'] ) ) {
					$links['associated-pages']['talk'] = $newLink;
				} else {
					$links['namespaces']['talk'] = $newLink;
				}
			}
		}
	}
}
