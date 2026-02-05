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

		$targetNamespaces = $this->config->getTargetNamespaces() ?: [ 0 ];
		$title = $skin->getTitle();
		if ( !in_array( $title->getNamespace(), $targetNamespaces ) ) {
			return;
		}

        if ( !in_array( strtolower( $skin->getSkinName() ), $this->config->getTargetSkins() ) ) {
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
			'class' => ''
		];
        
        if ( isset( $links['associated-pages'] ) ) {
            $links['associated-pages']['talk'] = $newLink;
            if ( isset( $links['associated-pages']['discussion'] ) ) {
                unset( $links['associated-pages']['discussion'] );
            }
        }

		if ( isset( $links['namespaces']['talk'] ) ) {
			$links['namespaces']['talk'] = $newLink;
		}
		
		if ( isset( $links['namespaces']['discussion'] ) ) {
			unset( $links['namespaces']['discussion'] );
		}
	}
}
