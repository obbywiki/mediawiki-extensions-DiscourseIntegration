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

		$talkNamespaces = $this->config->getTalkNamespaces();
		$title = $skin->getTitle();
		if ( !in_array( $title->getNamespace(), $talkNamespaces ) ) {
			return;
		}

        $skinName = strtolower( $skin->getSkinName() );
        if ( !in_array( $skinName, $this->config->getTalkSkins() ) ) {
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

		$newLink = [
			'href' => $discourseUrl . '/search?q=' . urlencode( $titleText ),
			'text' => 'Discourse',
			'title' => wfMessage( 'discourseintegration-discourse-button-alt' )->text(),
			'rel' => 'discussion',
			'accesskey' => 't',
			'id' => 'ca-talk',
			'class' => 'discourse-talk-link'
		];
        
		$skin->getOutput()->addModuleStyles( [ 'ext.DiscourseIntegration.styles' ] );

		
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
