<?php
/**
 * BlankPages
 *
 * Created on June 20th, 2010
 *
 * @license http://krinkle.mit-license.org/
 * @author Timo Tijhof, 2010-2014
 */

/**
 * Configuration
 * -------------------------------------------------
 */
// BaseTool & Localization
require_once( __DIR__ . '/../lib/basetool/InitTool.php' );
require_once( KR_TSINT_START_INC );

$toolConfig = array(
	'displayTitle' => 'BlankPages',
	'krinklePrefix' => false,
	'remoteBasePath' => dirname( $kgConf->getRemoteBase() ). '/',
	'revisionId' => '0.3.0',
);

$kgTool = BaseTool::newFromArray( $toolConfig );
$kgTool->setSourceInfoGithub( 'Krinkle', 'mw-tool-blankpages', __DIR__ );

$kgTool->doHtmlHead();
$kgTool->doStartBodyWrapper();


/**
 * Setup
 * -------------------------------------------------
 */


function wikiurl($s){
	return str_replace('%2F', '/', rawurlencode($s));
}

// Settings
$s = array();

$s['ns'] = $kgReq->getVal('ns', '0|4|6|8|10|12|14|100|102|104');
$s['nsall'] = (int) $kgReq->getInt('nsall', 0);
if ( $s['nsall'] === 1 ) {
	$s['ns'] = null;
}
$s['p'] = $kgReq->getVal('p', 'commons.wikimedia');
$s['project'] = $kgReq->getVal('project', $s['p'] . '.org');
$s['limit'] = $kgReq->getInt('limit', 15);

if ($s['limit'] < 1) {
	// Invalid, use default
	$s['limit'] = 15;
} elseif ($s['limit'] > 50) {
	// Maximum
	$s['limit'] = 50;
}

if (!$kgReq->hasKey('deletereason')) {
	if (strpos($s['project'], 'nl.') !== false) {
		$deletereason = "Opruiming lege pagina's via [[m:User:Krinkle/Tools#BlankPages|Krinkle/BlankPages]]";
	} elseif (strpos($s['project'],'es.') !== false) {
		$deletereason = "Limpiando páginas en blanco usando [[m:User:Krinkle/Tools#BlankPages|Krinkle/BlankPages]]";
	} else {
		$deletereason = "Clean up blank pages via [[m:User:Krinkle/Tools#BlankPages|Krinkle/BlankPages]]";
	}
} else {
	// Only set deletereason in $s if it is different from the default
	// This to avoids a messy long permalink
	$s['deletereason'] = $deletereason = $kgReq->getVal('deletereason');
}

// App
$a = array();

$a['deletereason'] = $deletereason;

// Set urls
$a['api'] = "http://" . $s['project'] . "/w/api.php?format=json&";
$a['searchurl'] = $a['api'] . "action=query&list=allpages&apmaxsize=0&aplimit=" . $s['limit'];
$a['siurl'] = $a['api'] . "action=query&meta=siteinfo&siprop=namespaces";



/**
 * Output
 * -------------------------------------------------
 */

function getBlanks( $url ) {
	global $s, $a;

	$search = json_decode( file_get_contents( $url ), true );
	if ( !isset( $search['query'] ) ) {
		kfLog( 'Error in requesting data from ' . $url, __FUNCTION__ );
		return '';
	}
	$html = '';
	foreach ( $search['query']['allpages'] as $hit ) {
		$html .= '<li><small>('
			. "<a href='//" . $s['project'] . "/w/index.php?title=" . wikiurl( $hit['title'] ) . "&amp;action=history' target='_blank'>history</a>"
			. " | <a href='//" . $s['project'] . "/w/index.php?title=" . wikiurl( $hit['title'] ) . "&amp;action=delete&amp;wpReason=".urlencode( $a['deletereason'] )."' target='_blank'>delete</a>"
			. ")</small> "
			. " <a href='//" . $s['project'] . "/wiki/" . wikiurl( $hit['title'] ) . "' target='_blank'>" . htmlspecialchars( $hit['title'] )
			. "</a></li>";

	}
	return $html;
}

$search = json_decode( file_get_contents( $a['siurl'] ), true );

// Display namespaces and highlight current single selected, (all) or nothing if selection has multiple
$a['namespaces'] = array();
// Start output
$output = '<div style="-moz-column-count: 3; -webkit-column-count: 3; column-count: 3;"><small>';

// Loop through the namespaces on this wiki
foreach ( $search["query"]["namespaces"] as $hit ) {

	$a['namespaces'][] = $hit['id'];
	if (
		$s['nsall'] === 0 && (
			// if this hit is in the ns setting, bold it's name in the output
			$s['ns'] == $hit['id'] || in_array($hit['id'], explode('|', $s['ns']))
		)
	) {
		$hit['*'] = "<strong>" . $hit['*'] . "</strong>";
	}
	// Add the permalink
	$output .= $hit['id'] . " : <a href='" . htmlspecialchars( $kgTool->generatePermalink( array( 'ns' => $hit['id'], 'nsall' => 0 ) + $s ) ) . "'>" . $hit['*'] . "</a><br/>";
}
// Finish output
$output .= "</small></div>";

$all = $s['nsall'] === 1 ? '<strong>(all)</strong>' : '(all)';

// Wrap output
$output = $s['project'] . " has these namespaces: " . implode(', ', $a['namespaces'] ) . "<br/> <a href='" . htmlspecialchars($kgTool->generatePermalink( array( 'ns' => null, 'nsall' => 1 ) + $s )) . "'>" . $all."</a> " . $output."<hr/>";


// Warnings
$output .= '<strong>- Be sure to check the history</strong><br/><strong>- Don\'t delete talkpages just for being blank.</strong>';
if ( $s['project'] == 'commons.wikimedia.org' ) {
	$output .= '<br/><strong>- Speedy guidelines: <a href="//commons.wikimedia.org/wiki/COM:SPEEDY#Speedy_deletion" target="_blank">//commons.wikimedia.org/wiki/COM:SPEEDY#Speedy_deletion</a></strong>';
}
$output .= '<hr/><br/>';


$results = "";

$queryNs = $s['nsall'] === 1 ? $a['namespaces'] : explode('|', $s['ns']);
foreach ( $queryNs as $ns ) {
	// Don't query the API for pages in NS_SPECIAL (-1) or NS_MEDIA (-2)
	if ( $ns >= 0 ) {
		$results .= getBlanks( $a['searchurl'] . '&apnamespace=' . $ns );
	}
}

if ( $results === '' ) {
	$output .= '<ul><li><em>No results.</em></li></ul>';
} else {
	$output .= '<ul>' . $results . '</ul>';
}

$kgTool->addOut( $output );


/**
 * Close up
 * -------------------------------------------------
 */
$kgTool->flushMainOutput();

