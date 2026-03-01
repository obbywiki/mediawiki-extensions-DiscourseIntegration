<?php

namespace MediaWiki\Extension\DiscourseIntegration;

use LogicException;
use MediaWiki\Config\ServiceOptions;

class ExtensionConfig {
	public const SERVICE_NAME = 'DiscourseIntegrationConfig';
	public const API_KEY = 'DiscourseAPIKey';
	public const API_USERNAME = 'DiscourseAPIUsername';
	public const BASE_URL = 'DiscourseBaseURL';
	public const REPLACE_TALK_PAGES = 'DiscourseReplaceTalkPages';
	public const TARGET_NAMESPACES = 'DiscourseTargetNamespaces';
    public const TARGET_SKINS = 'DiscourseTargetSkins';
	public const EXCLUDE_STRINGS = 'DiscourseExcludeStrings';
    public const EXCLUDE_PAGES = 'DiscourseExcludePages';
	public const SQUARE_PFP_FOR_ALL = 'DiscourseSquarePFPsForAll';
	public const SQUARE_PFP_FOR_USERS_WITH_TITLES = 'DiscourseSquarePFPsForUsersWithTitles';
    public const USE_NO_FOLLOW_ON_FORUM_LINKS = 'DiscourseUseNoFollowOnForumLinks';
    public const OPEN_FORUM_LINKS_IN_NEW_TAB = 'DiscourseOpenForumLinksInNewTab';
	public const CACHE_TTL = 'DiscourseCacheTTL';
	public const TOPIC_SORT_ORDER = 'DiscourseTopicSortOrder';
	public const CONSTRUCTOR_OPTIONS = [
		self::API_KEY,
		self::API_USERNAME,
		self::BASE_URL,
		self::REPLACE_TALK_PAGES,
		self::TARGET_NAMESPACES,
		self::TARGET_SKINS,
		self::EXCLUDE_STRINGS,
		self::EXCLUDE_PAGES,
		self::SQUARE_PFP_FOR_ALL,
		self::SQUARE_PFP_FOR_USERS_WITH_TITLES,
		self::USE_NO_FOLLOW_ON_FORUM_LINKS,
		self::OPEN_FORUM_LINKS_IN_NEW_TAB,
		self::CACHE_TTL,
		self::TOPIC_SORT_ORDER,
	];

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getApiKey(): string {
		$apiKey = $this->options->get( self::API_KEY );
		if ( $apiKey === false ) {
			throw new LogicException( '$wgDiscourseAPIKey must be set' );
		}
		return $apiKey;
	}

	public function getApiUsername(): string {
		return $this->options->get( self::API_USERNAME );
	}

	public function getBaseUrl(): string {
		$baseUrl = $this->options->get( self::BASE_URL );
		if ( $baseUrl === false ) {
			throw new LogicException( '$wgDiscourseBaseUrl must be set' );
		}
		return $baseUrl;
	}

	public function getReplaceTalkPages(): bool {
		return $this->options->get( self::REPLACE_TALK_PAGES );
	}

	public function getTargetNamespaces(): array {
		return $this->options->get( self::TARGET_NAMESPACES ) ?: [ 0 ];
	}

    public function getTargetSkins(): array {
        return $this->options->get( self::TARGET_SKINS ) ?: [ 'citizen', 'vector' ];
    }

	public function getExcludeStrings(): array {
		return $this->options->get( self::EXCLUDE_STRINGS ) ?: [];
	}

	public function getExcludePages(): array {
		return $this->options->get( self::EXCLUDE_PAGES ) ?: ["Main_Page", "Home", "Main", "Mainpage"];
	}

    public function getSquarePFPForAll(): bool {
        return $this->options->get( self::SQUARE_PFP_FOR_ALL ) ?: false;
    }

    public function getSquarePFPForUsersWithTitles(): array {
        return $this->options->get( self::SQUARE_PFP_FOR_USERS_WITH_TITLES ) ?: [];
    }

    public function getUseNoFollowOnForumLinks(): bool {
        return $this->options->get( self::USE_NO_FOLLOW_ON_FORUM_LINKS ) ?: false;
    }

    public function getOpenForumLinksInNewTab(): bool {
        return $this->options->get( self::OPEN_FORUM_LINKS_IN_NEW_TAB ) ?: false;
    }

	public function getCacheTTL(): int {
		return (int)( $this->options->get( self::CACHE_TTL ) ?: 3600 );
	}

	public function getTopicSortOrder(): string {
		return $this->options->get( self::TOPIC_SORT_ORDER ) ?: '';
	}
}