<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

$wgExtensionCredits['other'][] = array(
	'name' => 'SigChecker',
	'author' => '[http://rationalwiki.com/wiki/User:Nx Nx]',
	'url' => 'http://rationalwiki.com',
	'description' => 'Checks for signatures'
);


$wgSigCheckerIP = dirname( __FILE__ );
$wgExtensionMessagesFiles['SigChecker'] = "$wgSigCheckerIP/SigChecker.i18n.php";
$wgAutoloadClasses['SigCheckerBlacklist'] = "$wgSigCheckerIP/SigChecker.blacklist.php";

## Normal edit hooks
if ( defined( 'MW_SUPPORTS_EDITFILTERMERGED' ) ) {
	$wgHooks['EditFilterMerged'][] = 'SigCheckEditFilterMerged';
} else {
	$wgHooks['EditFilter'][] = 'SigCheckEditFilter';
}

$wgHooks['ArticleSaveComplete'][] = 'SigCheckerClearBlacklist';

function SigCheckerClearBlacklist( &$article, &$user, $text, $summary, $isminor, $iswatch, $section ) {
	$title = $article->getTitle();
	if ( $title->getNamespace() == NS_MEDIAWIKI && $title->getDBkey() == 'Unsigblacklist' ) {
		global $wgUnsigBlacklist;
		SigCheckerInitBlacklist();
		$wgUnsigBlacklist->invalidate();
	}
	return true;
}

function SigCheckerInitBlacklist() {
	global $wgUnsigBlacklist;
	if ( isset( $wgUnsigBlacklist ) && $wgUnsigBlacklist ) {
		return;
	}
	$wgUnsigBlacklist = new SigCheckerBlacklist();
}

function SigCheckEditFilter( $editor, $text, $section, &$error, $summary ) {
	return true;
}

function SigCheckEditFilterMerged( $editor, $text, &$error, $summary ) {
	global $wgUser;

	global $wgUnsigBlacklist;
	SigCheckerInitBlacklist();

	# global $wgOut;

	# $wgOut->addWikiText(implode("," , $wgUnsigBlacklist->getBlacklist()));
	# $wgOut->addWikiText($wgUser->getName());
	# $wgOut->addWikiText($wgUnsigBlacklist->isBlacklisted($wgUser->getName()));
	# $wgOut->addWikiText($editor->getArticle()->getTitle()->isTalkPage());
	# $editor->showEditForm();
	# return false;

	if ( !$wgUnsigBlacklist->isBlacklisted( $wgUser->getName() ) ) {
		return true;
	}

	if ( !$editor->getArticle()->getTitle()->isTalkPage() ) {
		return true;
	}

	$oldtext = $editor->getArticle()->fetchContent();
	$newtext = $text;
	if ( $oldtext !== false || $newtext != '' ) {
		$de = new DifferenceEngine( $editor->getArticle()->getTitle() );
		$de->setText( $oldtext, $newtext );
		$difftext = $de->getDiff( $oldtitle, $newtitle );
		// $de->showDiffStyle();
	} else {
		return true;
	}
	global $wgOut;

	$splits = preg_split( '/<tr><td colspan=.2. class=.diff-lineno.>[^<]*<\/td>\s*<td colspan=.2. class=.diff-lineno.>[^<]*<\/td><\/tr>/', $difftext );
	$nosig = false;
	foreach ( $splits as $part ) {
		// $wgOut->addHTML('<div>' . htmlspecialchars($part) . '</div>');
		$foundunsigned = false;
		$firstone = true;
		$sigspam = false;
		$trows = explode( '<tr>', $part );
		foreach ( $trows as $trow ) {
			// $wgOut->addHTML('<div>' . 'row' . htmlspecialchars($trow) . '</div>');
			$addedline = false;
			if ( strpos( $trow, '<td class=\'diff-addedline\'>' ) !== FALSE ) {
				if ( strpos( $trow, '<td class=\'diff-deletedline\'>' ) === FALSE ) {
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
						$foundunsigned = true;
					} else {
						// found a sig, so foundunsig is false now
						if ( substr_count( $trow , '~~~~' ) > 1 ) {
							// thinks he is clever and spams 4 tildes with spaces between them
							$foundunsigned = true;
							$sigspam = true;
						} else {
							if ( !$firstone ) {
								# $wgOut->addHTML('<div>' . 'not first' . htmlspecialchars($trow) . '</div>');
								// was the previous line unsigned?
								# if ($foundunsigned) { $wgOut->addHTML('<div> fu' . '</div>'); } else { $wgOut->addHTML('<div> fu false' . '</div>'); }
								if ( $foundunsigned && !$sigspam ) {
									// $wgOut->addHTML('<div>' . 'signed' . '</div>');
									$foundunsigned = false;
								} else {
									// then why the hell are you signing twice?
									// $wgOut->addHTML('<div>' . 'signing twice: ' . htmlspecialchars($matches[1]) . '</div>');
									// return false;
									if ( trim( $matches[1] ) == '~~~~' ) {
										$foundunsigned = true;
										$sigspam = true;
									} else {
										$foundunsigned = false;
									}
								}
							} else {
								// $wgOut->addHTML('<div>' . 'first' . htmlspecialchars($trow) . '</div>');
								$foundunsigned = false;
							}
						}
					}
					$firstone = false;
				}
			} else {
				// modified line or unmodified line
				// if found unsigned rows before, then there's a missing sig
				if ( $foundunsigned ) {
					$nosig = true;
				}
			}
		}
		if ( $foundunsigned ) {
			$nosig = true;
		}
	}
/*	if ($nosig) {
		$wgOut->addHTML('<div>no sig</div>');
	} else {
		$wgOut->addHTML('<div>sig</div>');
	}
	return false;*/
	if ( $nosig ) {
		$text = wfMessage( 'unsignednotice' )->parse();
		$wgOut->addHTML( $text );
		$editor->showEditForm();
		return false;
	} else {
		return true;
	}
}

