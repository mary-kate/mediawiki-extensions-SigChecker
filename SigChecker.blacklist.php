<?php

class SigCheckerBlacklist {

	private $mBlacklist = null;

	public function load() {
		global $wgMemc;
		wfProfileIn( __METHOD__ );
		// Try to find something in the cache
		$cachedBlacklist = $wgMemc->get( wfMemcKey( 'sigchecker_blacklist_entries' ) );
		if ( is_array( $cachedBlacklist ) && count( $cachedBlacklist ) > 0 ) {
			$this->mBlacklist = $cachedBlacklist;
			wfProfileOut( __METHOD__ );
			return;
		}

		$this->mBlacklist = array();
		$this->mBlacklist = $this->parseBlacklist( $this->getBlacklistText() );
		$wgMemc->set( wfMemcKey( 'sigchecker_blacklist_entries' ), $this->mBlacklist, 100 );
		wfProfileOut( __METHOD__ );
	}

	private static function getBlacklistText( ) {
		return wfMessage( 'unsigblacklist' )->inContentLanguage()->text();
	}

	public static function parseBlacklist( $list ) {
		wfProfileIn( __METHOD__ );
		$lines = preg_split( "/\r?\n/", $list );
		$result = array();
		foreach ( $lines as $line ) {
			$line = preg_replace( "/^\\s*([^#]*)\\s*((.*)?)$/", "\\1", $line );
			$line = trim( $line );
			if ( $line ) {
				$result[] = $line;
			}
		}

		wfProfileOut( __METHOD__ );
		return $result;
	}

	public function isBlacklisted( $username ) {
		$blacklist = $this->getBlacklist();
		foreach ( $blacklist as $item ) {
			if ( $item == $username ) {
				return true;
			}
		}
		return false;
	}

	public function getBlacklist() {
		if ( is_null( $this->mBlacklist ) ) {
			$this->load();
		}
		return $this->mBlacklist;
	}

	public function invalidate() {
		global $wgMemc;
		$wgMemc->delete( wfMemcKey( 'sigchecker_blacklist_entries' ) );
	}
}
