<?php
/**
 * BlankPages
 *
 * Created on June 20th, 2010
 *
 * @license http://krinkle.mit-license.org/
 * @author Timo Tijhof, 2010–2013
 */

/**
 *  Configuration
 * -------------------------------------------------
 */

session_start();
error_reporting(0);//DEBUG: error_reporting(-1)
date_default_timezone_set('UTC');

$revID = '0.2.1';
$revDate = '2012-01-30';

$c['title'] = 'BlankPages';
$c['baseurl'] = 'http://toolserver.org/~krinkle/BlankPages.php';

/**
 *  Functions
 * -------------------------------------------------
 */
function CacheAndDefault($variable = false, $default = false, $cache = false){
        if ( !empty($variable) ) {
                return $variable;
        } elseif ( !empty($cache) ) {
                return $cache;
        } else {
                return $default;
        }
}
function wikiurl($s){
        return str_replace('%2F', '/', rawurlencode($s));
}

function BuiltPermNS($nsNow = 0){
        global $s; global $c;
        $l = $c['baseurl'] . '?action=view';
        foreach($s as $key=>$val){
                if( $key == 'ns' && !empty($nsNow) ){
                        $l .= '&' . $key.'='.urlencode($nsNow);
                } else {
                        $l .= '&' . $key.'='.urlencode($val);
                }
        }
        return $l;
}


/**
 *  Settings
 * -------------------------------------------------
 */
unset($s['allns']);
$s['ns'] = CacheAndDefault(strtolower(strip_tags($_GET['ns'])), '0|4|6|8|10|12|14|100|102|104');
$s['nsall'] = !empty($_GET['nsall']) ? 1 : 0;
if ( $s['nsall'] === 1 ) { 
        unset($s['ns']);
}
$s['p'] = !empty($_GET['p']) ? htmlentities(strtolower(strip_tags($_GET['p']))) : 'commons.wikimedia';
$s['project'] = !empty($_GET['project']) ? htmlentities(strtolower(strip_tags($_GET['project']))) : $s['p'] . '.org';
$s['limit'] = intval(CacheAndDefault($_GET['limit'], 15)); if(!is_int($s['limit']) || $s['limit'] < 1) $s['limit'] = 15;
if($s['limit'] > 50) $s['limit'] = 50;

if(empty($_GET['deletereason']) && strpos($s['project'],'nl.') !== false){
        $deletereason = "Opruiming lege pagina's via [[m:User:Krinkle/Tools#BlankPages|Krinkle/BlankPages]]";
} elseif(empty($_GET['deletereason']) && strpos($s['project'],'es.') !== false){
        $deletereason = "Limpiando páginas en blanco usando [[m:User:Krinkle/Tools#BlankPages|Krinkle/BlankPages]]";
} else {
        $deletereason = "Clean up blank pages via [[m:User:Krinkle/Tools#BlankPages|Krinkle/BlankPages]]";
}

// Only set $s['deletereason'] if it's not the default or empty
// This to avoid a messy long permalink
if(!empty($_GET['deletereason']) && $_GET['deletereason'] !== $deletereason){
        $deletereason = $s['deletereason'] = $_GET['deletereason'];
}

$c['permalink'] = BuiltPermNS(0);

// Default or not, we do need it below in the app
$s['deletereason'] = $deletereason


?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
        <meta charset="utf-8">
        <title>Krinkle - <?=$c['title']?></title>
        <link rel="stylesheet" href="main.css">
</head>
<body>
        <div id="page-wrap">
                
                <h1><small>Krinkle</small><a href="<?=htmlspecialchars($c['baseurl']);?>"> | <?=$c['title']?></a></h1>
                <small><em>Version <?=$revID?> as  uploaded on <?=$revDate?> by Krinkle</em><?php if($c['permalink']) echo "| <a href='" . htmlspecialchars($c['permalink']) . "'>Permalink to this page</a>";?></small>
                <hr/>

<?php
        // Output settings
        echo 'Config:<br/><pre>';
        foreach( $c as $key => $val ) {
                echo htmlspecialchars(' ' . $key . ': ' . $val ) . '<br/>';
        }
        echo '</pre>';
        echo 'Settings:<br/><pre>';
        foreach( $s as $key => $val ) {
                // Temporary hack
                if ( $key == 'limit' ) {
                        $key = 'Results per namespace';
                }
                echo " " . $key . ": " . $val . "<br/>";
        }
        echo "</pre>";
        
        // Set urls
        $a['api'] = "http://" . $s['project'] . "/w/api.php?format=php&";
        $a['searchurl'] = $a['api'] . "action=query&list=allpages&apmaxsize=0&aplimit=" . $s['limit'];
        $a['siurl'] = $a['api'] . "action=query&meta=siteinfo&siprop=namespaces";
        
        // Get Site info
        ini_set( 'user_agent', 'KrinkleTools/0.1; krinklemail [at] gmail [.] com' );
        $search = file_get_contents( $a['siurl'] );
        $search = unserialize( $search );
        
        // Display namespaces and highlight current single selected, (all) or nothing if selection has multiple
                $s['allns'] = "";
                $output = "<div style='column-count:3; -moz-column-count:3; -webkit-column-count:3;'><small>"; // Start output
                
                // Loop through the namespaces on this wiki
                foreach( $search["query"]["namespaces"] as $hit ) {
                
                        // Append it to the allns-variable which will at the end contain all namespaces on this wiki seperated by pipe
                        $s['allns'] .= $hit['id'] . "|";
                        // if this hit is equal to the ns-setting, bold it's name in the output
                        if(     ( $s['ns'] == $hit['id'] || in_array($hit['id'], explode('|', $s['ns']) ) ) && $s['nsall'] == 0 ) {
                                $hit['*'] = "<strong>" . $hit['*'] . "</strong>";
                        }
                        // Add the permalink 
                        $output .= $hit['id'] . " : <a href='" . htmlspecialchars( BuiltPermNS( $hit['id'] ) ) . "&amp;nsall=0'>" . $hit['*'] . "</a><br/>";
                }
                $output .= "</small></div>"; // Finish output
                
                // Echo output
                $all = $s['nsall'] == 1 ? '<strong>(all)</strong>' : '(all)';
                echo "<hr/>" . $s['project'] . " has these namespaces: " . $s['allns'] . "<br/> <a href='" . htmlspecialchars($c['permalink']) . "&amp;nsall=1'>" . $all."</a> " . $output."<hr/>";
        
        
        // Warnings
        echo '<strong>- Be sure to check the history</strong><br/><strong>- Don\'t delete talkpages just for being blank.</strong>';
        if( $s['project'] == 'commons.wikimedia.org' ) {
                echo '<br/><strong>- Speedy guidelines: <a href="//commons.wikimedia.org/wiki/COM:SPEEDY#Speedy_deletion" target="_blank">//commons.wikimedia.org/wiki/COM:SPEEDY#Speedy_deletion</a></strong>';
        }
        echo '<hr/><br/>';
        
        $a['output'] = "<ul>";
        
        function GetBlanks( $url ) {
                global $c, $s, $a;

                ini_set( 'user_agent', 'KrinkleTools/0.1; krinklemail [at] gmail [.] com' );
                $search = file_get_contents($url);
                $search = unserialize($search);
                foreach( $search['query']['allpages'] as $hit ) {
                        $a['output'] .= '<li><small>('
                                . "<a href='//" . $s['project'] . "/w/index.php?title=" . wikiurl( $hit['title'] ) . "&amp;action=history' target='_blank'>history</a>"
                                . " | <a href='//" . $s['project'] . "/w/index.php?title=" . wikiurl( $hit['title'] ) . "&amp;action=delete&amp;wpReason=".urlencode( $s['deletereason'] )."' target='_blank'>delete</a>"
                                . ")</small> "
                                . " <a href='//" . $s['project'] . "/wiki/" . wikiurl( $hit['title'] ) . "' target='_blank'>" . htmlspecialchars( $hit['title'] )
                                . "</a></li>";
                        
                }
        }
        
        if( $s['nsall'] == 1 ) {
                $s['ns'] = $s['allns'];
        }

        $s['ns'] = explode('|', $s['ns']);
        foreach( $s['ns'] as $ns ){
                GetBlanks( $a['searchurl'] . '&apnamespace=' . $ns );
        }
        
        if( empty($a['output']) )
                echo '<li><em>No results.</em></li>';
        else
                echo $a['output'];
        
        
        echo "</ul>";
                
?>
                
                <h3 id="author">Author</h3>
                        <p><?=$c['title']?> by <a data-cc="http://creativecommons.org/ns#" href="//meta.wikimedia.org/wiki/User:Krinkle" data-property="cc:attributionName" data-rel="cc:attributionURL">Krinkle</a> is released in the public domain.</p>
                        <hr/>
                        <p>Contact me at <em>krinklemail<img src="//upload.wikimedia.org/wikipedia/commons/thumb/8/88/At_sign.svg/15px-At_sign.svg.png" alt="at"/>gmail&middot;com</em>, or leave a message on the <a href="//meta.wikimedia.org/w/index.php?title=User_talk:Krinkle/Tools&amp;action=edit&amp;section=new&amp;editintro=User_talk:Krinkle/Tools/Editnotice&amp;preload=User_talk:Krinkle/Tools/Preload">Tools feedback page</a>.</p>
        </div>
<a href="<?=htmlspecialchars($c['baseurl']);?>" style="display:none;" id="home">Reload</a>
</body>
</html>
