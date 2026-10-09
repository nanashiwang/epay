<?php
namespace lib;

/** Public, read-only guides; only allowlisted repository files are rendered. */
final class HelpCenter
{
    public static function directory() { return dirname(__DIR__,2).'/docs/help/'; }
    public static function catalog()
    {
        return json_decode(file_get_contents(self::directory().'catalog.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public static function article($id)
    {
        if (!is_string($id) || !preg_match('/\A[a-z][a-z0-9_]*\z/D',$id)) return null;
        foreach (self::catalog()['articles'] as $a) {
            if ($a['id']===$id) { $a['body']=file_get_contents(self::directory().$id.'.md'); return $a; }
        }
        return null;
    }
    public static function escape($value) { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public static function url($id='') { return '/index.php?doc=help'.($id!==''?'&topic='.rawurlencode($id):''); }
    public static function search($query)
    {
        $words=preg_split('/\s+/u',trim($query),-1,PREG_SPLIT_NO_EMPTY);
        $found=[];
        foreach (self::catalog()['articles'] as $a) {
            $text=mb_strtolower($a['title'].' '.$a['summary'].' '.implode(' ',$a['keywords']).' '.file_get_contents(self::directory().$a['id'].'.md'));
            $matches=true;
            foreach ($words as $word) if (mb_strpos($text,mb_strtolower($word))===false) { $matches=false; break; }
            if ($matches) $found[]=$a;
        }
        return $found;
    }
    public static function link($url)
    {
        if (preg_match('/\A([a-z][a-z0-9_]*)\.md\z/D',$url,$m)) return self::article($m[1])?self::url($m[1]):null;
        if (preg_match('/[\x00-\x20\x7f\\\\]/',$url)) return null;
        if (str_starts_with($url,'/') && !str_starts_with($url,'//')) return $url;
        if (str_starts_with($url,'#')) return $url;
        $parts=parse_url($url);
        return $parts && ($parts['scheme']??'')==='https' && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])?$url:null;
    }
    public static function inline($text)
    {
        $html='';
        foreach (preg_split('~(`[^`]+`|\*\*[^*]+\*\*|\[[^\]]+\]\([^\s)]+\))~u',$text,-1,PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if (preg_match('~\A\[([^\]]+)\]\(([^\s)]+)\)\z~u',$part,$m)) {
                $url=self::link($m[2]);
                $html.=$url!==null?'<a href="'.self::escape($url).'"'.(str_starts_with($url,'https://')?' rel="noopener noreferrer"':'').'>'.self::escape($m[1]).'</a>':self::escape($m[1]);
            } elseif (str_starts_with($part,'`') && str_ends_with($part,'`')) $html.='<code>'.self::escape(substr($part,1,-1)).'</code>';
            elseif (str_starts_with($part,'**') && str_ends_with($part,'**')) $html.='<strong>'.self::escape(substr($part,2,-2)).'</strong>';
            else $html.=self::escape($part);
        }
        return $html;
    }
    public static function render($markdown)
    {
        $lines=explode("\n",str_replace("\r\n","\n",$markdown));$html='';$toc=[];$n=count($lines);
        for ($i=0;$i<$n;$i++) {
            $line=trim($lines[$i]);
            if ($line==='' || str_starts_with($line,'# ')) continue;
            if (str_starts_with($line,'```')) {
                $code=[];while (++$i<$n && !str_starts_with(trim($lines[$i]),'```')) $code[]=$lines[$i];
                $html.='<pre><code>'.self::escape(implode("\n",$code)).'</code></pre>';continue;
            }
            if (preg_match('/\A(#{2,3}) (.+)\z/u',$line,$m)) {
                $id='section-'.(count($toc)+1);$level=strlen($m[1]);$toc[]=['id'=>$id,'title'=>$m[2],'level'=>$level];
                $html.='<h'.$level.' id="'.$id.'">'.self::inline($m[2]).'</h'.$level.'>';continue;
            }
            if (str_starts_with($line,'|') && isset($lines[$i+1]) && preg_match('/\A\|[\s:|\-]+\|\z/',trim($lines[$i+1]))) {
                $cells=static fn($s)=>array_map('trim',explode('|',trim(trim($s),'|')));
                $html.='<div class="help-table" tabindex="0" role="region" aria-label="对照表，可横向滚动"><table><thead><tr>';
                foreach ($cells($line) as $c) $html.='<th scope="col">'.self::inline($c).'</th>';
                $html.='</tr></thead><tbody>';$i+=2;
                for (;$i<$n && str_starts_with(trim($lines[$i]),'|');$i++) {
                    $html.='<tr>';foreach ($cells($lines[$i]) as $c) $html.='<td>'.self::inline($c).'</td>';$html.='</tr>';
                }
                $i--;$html.='</tbody></table></div>';continue;
            }
            if (preg_match('/\A(- |\d+\. )(.+)\z/u',$line,$m)) {
                $ordered=$m[1]!=='- ';$tag=$ordered?'ol':'ul';$pattern=$ordered?'/\A\d+\. (.+)\z/u':'/\A- (.+)\z/u';$html.='<'.$tag.'>';
                for (;$i<$n && preg_match($pattern,trim($lines[$i]),$item);$i++) $html.='<li>'.self::inline($item[1]).'</li>';
                $i--;$html.='</'.$tag.'>';continue;
            }
            $html.='<p>'.self::inline($line).'</p>';
        }
        return ['html'=>$html,'toc'=>$toc];
    }
}
