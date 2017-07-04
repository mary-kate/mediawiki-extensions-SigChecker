<?php

class SigChecker {

	/**
	 * Purges caches when [[MediaWiki:Unsigblacklist]] is edited.
	 */
	public static function onPageContentSaveComplete( $article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId ) {
		$title = $article->getTitle();
		if ( $title->getNamespace() == NS_MEDIAWIKI && $title->getDBkey() == 'Unsigblacklist' ) {
			global $wgUnsigBlacklist;
			SigChecker::initBlacklist();
			$wgUnsigBlacklist->invalidate();
		}
		return true;
	}

	/**
	 * Initializes a new instance of the SigCheckerBlacklist class into the
	 * global variable $wgUnsigBlacklist if it hasn't already been initialized.
	 */
	public static function initBlacklist() {
		global $wgUnsigBlacklist;
		if ( isset( $wgUnsigBlacklist ) && $wgUnsigBlacklist ) {
			return;
		}
		$wgUnsigBlacklist = new SigCheckerBlacklist();
	}

	public static function onEditFilterMergedContent( $context, $content, $status, $summary, $user, $minoredit ) {
		global $wgOut, $wgUnsigBlacklist;

		SigChecker::initBlacklist();

		# global $wgOut;

		# $wgOut->addWikiText(implode("," , $wgUnsigBlacklist->getBlacklist()));
		# $wgOut->addWikiText($user->getName());
		# $wgOut->addWikiText($wgUnsigBlacklist->isBlacklisted($user->getName()));
		# $wgOut->addWikiText($editor->getArticle()->getTitle()->isTalkPage());
		# $editor->showEditForm();
		# return false;

		if ( !$wgUnsigBlacklist->isBlacklisted( $user->getName() ) ) {
			return true;
		}

		if ( !$context->canUseWikiPage() ) {
			return true;
		}

		$title = $context->getTitle();
		$wp = $context->getWikiPage();

		if ( !$context->getTitle()->isTalkPage() ) {
			return true;
		}

		$oldText = $wp->getContent();
		if ( !$oldText instanceof Content ) { // @todo FIXME: does not handle new page creations because $oldText is ''
			$oldText = ContentHandler::makeContent( $content->getNativeData(), $title );
		}

		$newText = $content->getNativeData();
		if ( $oldText !== false || $newText != '' ) {
			$de = new DifferenceEngine( $title );
			$de->setContent( $oldText, $content );
			$diffText = $de->getDiff( $title, $title );
			// $de->showDiffStyle();
		} else {
			return true;
		}

		$splits = preg_split( '/<tr><td colspan=.2. class=.diff-lineno.>[^<]*<\/td>\s*<td colspan=.2. class=.diff-lineno.>[^<]*<\/td><\/tr>/', $diffText );
		$noSig = false;
		foreach ( $splits as $part ) {
			// $wgOut->addHTML('<div>' . htmlspecialchars($part) . '</div>');
			$foundUnsigned = false;
			$firstOne = true;
			$sigSpam = false;
			$trRows = explode( '<tr>', $part );
			foreach ( $trRows as $trow ) {
				// $wgOut->addHTML('<div>' . 'row' . htmlspecialchars($trow) . '</div>');
				$addedline = false;
				if ( strpos( $trow, '<td class=\'diff-addedline\'>' ) !== false ) {
					if ( strpos( $trow, '<td class=\'diff-deletedline\'>' ) === false ) {
						$addedline = true;
					} else {
						if ( preg_match( '/<td class=.diff-deletedline.><div><del class=.diffchange.>(.*?)<\/del><\/div><\/td>/', $trow, $matches ) == 0 ) {
						// if (preg_match('/<td class=.diff-deletedline.>(.*?)<\/td>/',$trow,$matches) == 0) {
							$addedline = true;
						}
					}
				}
				if ( $addedline ) {
					// added line
					// $wgOut->addHTML('<div>' . htmlspecialchars($trow) . '</div>');
					preg_match( '/<td class=.diff-addedline.><div><ins class=.diffchange[^>]*>(.*?)<\/ins><\/div><\/td>/', $trow, $matches );
					# $wgOut->addHTML('<div>' . htmlspecialchars($matches[0]) . '</div>');
					# $wgOut->addHTML('<div>' . htmlspecialchars(strlen(trim($matches[1]))) . '</div>');
					if ( strlen( trim( $matches[1] ) ) != 0 ) {
						if ( strpos( $trow, '~~~~' ) === false || strpos( $trow, '~~~~~' ) !== false ) {
							// found a non-empty new row that's unsigned
							$foundUnsigned = true;
						} else {
							// found a sig, so foundUnsig is false now
							if ( substr_count( $trow , '~~~~' ) > 1 ) {
								// thinks he is clever and spams 4 tildes with spaces between them
								$foundUnsigned = true;
								$sigSpam = true;
							} else {
								if ( !$firstOne ) {
									# $wgOut->addHTML('<div>' . 'not first' . htmlspecialchars($trow) . '</div>');
									// was the previous line unsigned?
									# if ($foundUnsigned) { $wgOut->addHTML('<div> fu' . '</div>'); } else { $wgOut->addHTML('<div> fu false' . '</div>'); }
									if ( $foundUnsigned && !$sigSpam ) {
										// $wgOut->addHTML('<div>' . 'signed' . '</div>');
										$foundUnsigned = false;
									} else {
										// then why the hell are you signing twice?
										// $wgOut->addHTML('<div>' . 'signing twice: ' . htmlspecialchars($matches[1]) . '</div>');
										// return false;
										if ( trim( $matches[1] ) == '~~~~' ) {
											$foundUnsigned = true;
											$sigSpam = true;
										} else {
											$foundUnsigned = false;
										}
									}
								} else {
									// $wgOut->addHTML('<div>' . 'first' . htmlspecialchars($trow) . '</div>');
									$foundUnsigned = false;
								}
							}
						}
						$firstOne = false;
					}
				} else {
					// modified line or unmodified line
					// if found unsigned rows before, then there's a missing sig
					if ( $foundUnsigned ) {
						$noSig = true;
					}
				}
			}

			if ( $foundUnsigned ) {
				$noSig = true;
			}
		}

		if ( $noSig ) {
			$text = wfMessage( 'unsignednotice' )->parse();
			$wgOut->addHTML( $text );
			$editor = new EditPage( new Article( $title ) );
			$editor->showEditForm();
			return false;
		} else {
			return true;
		}
	}

}