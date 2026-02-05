<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\DiscourseIntegration\ExtensionConfig;
use MediaWiki\MediaWikiServices;

use MediaWiki\Extension\DiscourseIntegration\RelatedPosts;

return [
	ExtensionConfig::SERVICE_NAME => function ( MediaWikiServices $services ): ExtensionConfig {
		return new ExtensionConfig(
			new ServiceOptions(
				ExtensionConfig::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	RelatedPosts::SERVICE_NAME => function ( MediaWikiServices $services ): RelatedPosts {
		return new RelatedPosts(
			$services->getService( ExtensionConfig::SERVICE_NAME ),
			$services->getHttpRequestFactory(),
			$services->getMainWANObjectCache(),
			\MediaWiki\Logger\LoggerFactory::getInstance( 'DiscourseIntegration' )
		);
	},
];
