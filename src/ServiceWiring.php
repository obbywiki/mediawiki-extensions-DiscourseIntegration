<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\DiscourseIntegration\ExtensionConfig;
use MediaWiki\MediaWikiServices;

return [
	ExtensionConfig::SERVICE_NAME => function ( MediaWikiServices $services ): ExtensionConfig {
		return new ExtensionConfig(
			new ServiceOptions(
				ExtensionConfig::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
];
