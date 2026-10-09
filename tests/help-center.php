<?php
if (PHP_SAPI!=='cli') exit;
define('IN_CRONLITE',true);
require dirname(__DIR__).'/includes/lib/HelpCenter.php';
use lib\HelpCenter as Help;
$root=dirname(__DIR__).'/';$checks=0;
function check($ok,$label) { global $checks;if (!$ok) throw new RuntimeException('FAIL: '.$label);$checks++; }
$catalog=Help::catalog();$ids=array_column($catalog['articles'],'id');
check(count($ids)===22 && count(array_unique($ids))===22,'complete unique local article catalog');
foreach ($catalog['articles'] as $a) {
    $article=Help::article($a['id']);
    check($article && str_starts_with($article['body'],'# '.$a['title']."\n"),'catalog title matches '.$a['id']);
    check(in_array($a['category'],$catalog['categories'],true),'known category');
    $render=Help::render($article['body']);
    check(count($render['toc'])>=2 && strlen($render['html'])>200,'readable article '.$a['id']);
    check(!preg_match('/<(script|iframe|img)\b/i',$render['html']),'no executable source content');
    preg_match_all('~\[[^\]]+\]\(([^\s)]+)\)~u',$article['body'],$links);
    foreach ($links[1] as $url) {
        check(Help::link($url)!==null,'valid safe link '.$url);
        if (preg_match('/^\/doc\/([a-z_]+)\.html$/',$url,$m)) check(is_file($root.'template/default/doc/'.$m[1].'.php'),'existing API doc');
        elseif (str_starts_with($url,'/')) check(file_exists($root.ltrim(parse_url($url,PHP_URL_PATH),'/')),'existing project path '.$url);
    }
}
$sources=json_decode(file_get_contents(Help::directory().'sources.json'),true,512,JSON_THROW_ON_ERROR);
check(count($sources['pages'])===$sources['count'] && $sources['count']===97,'all 97 reviewed sources');
check(count(array_unique(array_column($sources['pages'],'url')))===97,'sources have unique URLs');
$sourceText=Help::article('sources')['body'];
foreach ($sources['pages'] as $s) {
    check($s['http_status']===200 && $s['chars']>0 && preg_match('/^[a-f0-9]{64}$/',$s['sha256']),'successful source evidence '.$s['slug']);
    check(in_array($s['scope'],['adapted','unsupported','retired','reference'],true) && in_array($s['guide'],$ids,true),'source applicability mapping');
    check(str_contains($sourceText,$s['url']),'source visible in help index');
}
foreach (['../config','../../config.php','alipay/../../config','%2e%2e',str_repeat('a',1000),['alipay']] as $id) check(Help::article($id)===null,'topic cannot select arbitrary file');
foreach (['javascript:alert(1)','data:text/html,test','//example.com','https://user@example.com','https://user:pass@example.com',"https://example.com\n/a",'../config.php','https:\\example.com'] as $url) check(Help::link($url)===null,'unsafe URL rejected');
$unsafe=Help::render("## 标题\n<img src=x onerror=alert(1)>\n[点击](javascript:alert)\n```html\n<script>alert(1)</script>\n```\n");
check(!str_contains($unsafe['html'],'<img') && !str_contains($unsafe['html'],'<script') && !str_contains($unsafe['html'],'href="javascript:'),'HTML and script payload inert');
check(str_contains($unsafe['html'],'&lt;script&gt;'),'code example escaped');
$hits=array_column(Help::search('微信 公钥'),'id');check(in_array('wechat_v3',$hits,true),'Chinese full text multiword search');
check(in_array('bepusdt',array_column(Help::search('trc20'),'id'),true),'case-insensitive search');
check(Help::search('unlikely_documentation_query_93619')===[],'empty search state');
function page($get) { global $root;$_GET=$get;$conf=['sitename'=>'Epay <test>'];http_response_code(200);ob_start();include $root.'template/default/doc/help.php';return [ob_get_clean(),http_response_code()]; }
[$html,$status]=page([]);check($status===200 && str_contains($html,'让收款配置有章可循'),'public help home without user session or DB');
check(str_contains($html,'Epay &lt;test&gt;'),'site title escaped');
[$html,$status]=page(['topic'=>'wechat_v3']);check($status===200 && str_contains($html,'PUB_KEY_ID_') && str_contains($html,'aria-current="page"'),'direct article and current navigation');
[$html,$status]=page(['topic'=>'../../config']);check($status===404 && str_contains($html,'文章不存在'),'invalid article 404');
[$html,$status]=page(['topic'=>['alipay']]);check($status===404,'array topic rejected');
[$html,$status]=page(['q'=>'unlikely_documentation_query_93619']);check($status===200 && str_contains($html,'没有找到匹配文章'),'empty results rendered');
[$html,$status]=page(['q'=>'"><script>alert(1)</script>']);check($status===200 && !str_contains($html,'<script>alert(1)</script>'),'query cannot inject markup');
[$html,$status]=page(['q'=>['x']]);check($status===400 && str_contains($html,'搜索词格式不正确'),'malformed query 400');
[$html,$status]=page(['q'=>str_repeat('测',121)]);check($status===400,'bounded search input');
foreach (glob($root.'template/default/doc/*.php') as $file) if (basename($file)!=='help.php') check(str_contains(file_get_contents($file),'/index.php?doc=help'),'API page links to help');
foreach (['head','channels','collection','bepusdt','groupbuy'] as $name) check(str_contains(file_get_contents($root.'user/'.$name.'.php'),'/index.php?doc=help'),'merchant entry '.$name);
echo "Help center: $checks checks passed\n";
