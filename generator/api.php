<?php
// مولد الملفات الرقمية - واجهة الكتابة
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok'=>false,'msg'=>'طلب غير صحيح']);
$cfg = require __DIR__.'/config.php';
$KEYS = array_values(array_filter(array_map('trim', (array)($cfg['keys'] ?? [$cfg['key'] ?? ''])))); $DAY = (int)($cfg['daily_limit'] ?? 400); $IPH = (int)($cfg['ip_hour_limit'] ?? 40);
// ===== حدود الاستخدام =====
$dir = __DIR__.'/data'; if (!is_dir($dir)) @mkdir($dir, 0700, true);
$ip = substr(preg_replace('/[^0-9a-f:.]/i','',$_SERVER['REMOTE_ADDR'] ?? 'x'),0,45);
$f = $dir.'/use-'.gmdate('Ymd').'.json';
$fp = fopen($f, 'c+'); flock($fp, LOCK_EX);
$u = json_decode(stream_get_contents($fp), true) ?: ['n'=>0,'ip'=>[]];
$h = gmdate('H'); $k = $ip.'|'.$h;
if ($u['n'] >= $DAY) { flock($fp, LOCK_UN); fclose($fp); out(['ok'=>false,'msg'=>'المولد وصل حده اليوم، جربي باكر إن شاء الله']); }
if (($u['ip'][$k] ?? 0) >= $IPH) { flock($fp, LOCK_UN); fclose($fp); out(['ok'=>false,'msg'=>'سويتي ملفات وايد، ارتاحي شوي وجربي بعد ساعة']); }
$u['n']++; $u['ip'][$k] = ($u['ip'][$k] ?? 0) + 1;
ftruncate($fp,0); rewind($fp); fwrite($fp, json_encode($u)); flock($fp, LOCK_UN); fclose($fp);
foreach (glob($dir.'/use-*.json') as $old) if ($old !== $f && filemtime($old) < time()-3*86400) @unlink($old);

$b = json_decode(file_get_contents('php://input'), true) ?: [];
function clean_in($s,$n){ $s = preg_replace('/[\x00-\x1f<>{}\[\]`]/u',' ',(string)$s); $s = trim(preg_replace('/\s+/u',' ',$s)); return mb_substr($s,0,$n); }
$mode = in_array($b['mode'] ?? '', ['single','outline','chunk']) ? $b['mode'] : 'single';
$topic = clean_in($b['topic'] ?? '',120); $extra = clean_in($b['extra'] ?? '',300); $custom = clean_in($b['custom'] ?? '',200);
$pages = (int)($b['pages'] ?? 1); if ($pages < 1 || $pages > 30) $pages = 1;
$G = [
 'planner'=>['مخطط جاهز للطباعة والتعبئة (يومي/أسبوعي/شهري حسب الموضوع) بجداول فيها خانات للكتابة باليد',1],
 'checklist'=>['قائمة تشييك جاهزة للتأشير بخطوات مرتبة منطقياً (كل الأقسام checklist=true)',0],
 'tracker'=>['جدول متابعة يومي أو أسبوعي بأعمدة للتعبئة وتتبع التقدم',1],
 'ebook'=>['دليل إلكتروني (كتيّب) يشرح الموضوع بالتسلسل من البداية للنتيجة بخطوات وأمثلة وأخطاء شائعة',0],
 'worksheet'=>['ورقة عمل تدريبية فيها تمارين وأسئلة وأنشطة تجاوب عليها المستخدمة',0],
 'templates'=>['مجموعة نصوص جاهزة للنسخ واللصق (رسائل وردود وكابشنات وعبارات) كل نص جاهز كامل باللهجة المناسبة',0],
 'recipes'=>['كتيّب وصفات: كل وصفة باسم جذاب ومكونات بكميات وخطوات مختصرة ووقت التحضير',0],
 'budget'=>['ميزانية وحسابات بجداول دخل ومصاريف وتوفير بخانات للتعبئة',1],
 'kids'=>['أنشطة أطفال تعليمية وتسلية بتعليمات بسيطة وآمنة مناسبة للأم والطفل',0],
 'custom'=>['',0]];
$type = isset($G[$b['type'] ?? '']) ? $b['type'] : 'planner';
if (mb_strlen($topic) < 3) out(['ok'=>false,'msg'=>'اكتبي الموضوع ولمنو الملف']);
$cp = (int)($b['cp'] ?? 0); if ($cp < 1 || $cp > 5) $cp = min($pages,5);
$chapter = clean_in($b['chapter'] ?? '',150); $dtitle = clean_in($b['dtitle'] ?? '',150);
$P = $mode==='chunk' ? $cp : $pages; $secs = $P*3;
$tbl = $type!=='custom' && $G[$type][1]===1;
$ICONS = ['star','check','heart','clock','list','idea','money','home','book','target','calendar','chat'];
$structure = ' كل قسم فيه: heading عنوان رئيسي قوي، subheading سطر فرعي قصير يوضح فايدة القسم، body فقرة مرتبة من جملتين سهلة القراءة، items نقاط عملية، icon واحدة من: '.implode(', ',$ICONS).' تناسب معنى القسم.';
$imgs = ' image_prompts: ثلاثة أوصاف قصيرة بالإنجليزية لصور فوتوغرافية جميلة مرتبطة مباشرة بالموضوع (الأول للغلاف)، كل وصف يذكر الأشياء والمكان بوضوح، بدون أشخاص وبدون نصوص.';
if ($type==='custom') $how = 'المطلوب من المستخدمة: '.$custom.'. نفذي المطلوب حرفياً داخل '.$secs.' أقسام بالضبط، كل قسم 4 إلى 5 نقاط.';
elseif ($tbl) $how = 'نوع الملف: '.$G[$type][0].'. الجدول إلزامي: فيه columns (من 3 إلى 5 أعمدة بعناوين واضحة) و rows فيه '.($P*10).' صفاً كاملاً محدداً وغير مكرر (خانات التعبئة تكون فارغة أو فيها شرطة). وضعي في sections قسمين أو ثلاثة عن طريقة الاستفادة منه ونصائح التعبئة.';
else $how = 'نوع الملف: '.$G[$type][0].'. فيه '.$secs.' أقسام بالضبط ليملأ '.$P.' صفحة، كل قسم 4 إلى 5 عناصر. اجعلي checklist=true للأقسام اللي هي قوائم مهام.';
$how .= $structure;
$style = 'معايير الجودة: العناوين قوية وتشد وفيها فايدة أو رقم؛ النقاط محددة وقابلة للتنفيذ فوراً بأرقام وأمثلة وأوقات بدون حشو أو تكرار؛ الأقسام مرتبة بتسلسل منطقي من البداية للنتيجة؛ العربية الفصحى البسيطة الدافية ومخاطبة المؤنثة؛ كل عنصر أقل من 22 كلمة بدون رموز في بدايته؛ معلومات صحيحة وآمنة بدون ادعاءات طبية أو قانونية أو مالية مضمونة ولا أسماء شركات.';
$base = "انتي مدربة محترفة وكاتبة محتوى منتجات رقمية تبيع. اكتبي محتوى قوياً ومؤثراً يخلي القارئة تقول «هذا بالضبط اللي أحتاجه» وتتحمس تستخدمه.\nالموضوع: ".$topic.($extra ? "\nإضافات تريدها المستخدمة (التزمي بها): ".$extra : '')."\n";
if ($mode==='outline') {
  $N = (int)ceil($pages/5);
  $prompt = $base.($type==='custom' ? 'المطلوب: '.$custom : 'نوع الملف: '.$G[$type][0])."\nالملف كبير (".$pages.' صفحة). جهزي المخطط فقط: عنوان قوي، عنوان فرعي بالنتيجة، مقدمة جملتين تلمس مشكلة القارئة وتعدها بالحل، conclusion خاتمة من جملتين تلخص وتحفز، نصيحة أخيرة ذكية، و chapters فيها '.$N.' عناوين فصول بالضبط، كل فصل محور مختلف تماماً ومتسلسل بدون تكرار.'.$imgs.' '.$style."\nارجعي JSON فقط.";
} elseif ($mode==='chunk') {
  $prompt = $base.'نكتب ملفاً بعنوان «'.$dtitle.'» على أجزاء. اكتبي فقط هذا الفصل: «'.$chapter.'» ('.$cp.' صفحات) ولا تكرري مواضيع الفصول الأخرى ولا تكتبي مقدمة أو خاتمة عامة. '.$how.' '.$style."\nارجعي JSON فقط.";
} else {
  $prompt = $base.'عدد الصفحات المطلوب: '.$pages."\n".$how.' '.$style."\nاكتبي title وsubtitle وintro (جملتان تلمسان مشكلتها وتعدانها بالحل) والأقسام ثم conclusion (خاتمة من 2 إلى 3 جمل تلخص أهم شي وتحفزها تبدأ) وtip (سر واحد ذكي).".$imgs.' ارجعي JSON فقط. إذا كان الموضوع غير لائق أو مؤذٍ أو غير مفهوم ارجعي فقط {"error":"اكتبي موضوع مناسب"}.';
}
// ===== طلب Gemini =====
$S = ['type'=>'STRING'];
$secSch = ['type'=>'ARRAY','items'=>['type'=>'OBJECT','properties'=>['heading'=>$S,'subheading'=>$S,'body'=>$S,'icon'=>['type'=>'STRING','enum'=>$ICONS],'checklist'=>['type'=>'BOOLEAN'],'items'=>['type'=>'ARRAY','items'=>$S]],'required'=>['heading','subheading','body','icon','items']]];
$tblSch = ['type'=>'OBJECT','properties'=>['columns'=>['type'=>'ARRAY','items'=>$S],'rows'=>['type'=>'ARRAY','items'=>['type'=>'OBJECT','properties'=>['cells'=>['type'=>'ARRAY','items'=>$S]],'required'=>['cells']]]],'required'=>['columns','rows']];
$imgSch = ['type'=>'ARRAY','items'=>$S];
if ($mode==='outline') { $props=['title'=>$S,'subtitle'=>$S,'intro'=>$S,'conclusion'=>$S,'tip'=>$S,'chapters'=>['type'=>'ARRAY','items'=>$S],'image_prompts'=>$imgSch]; $req=array_keys($props); }
elseif ($mode==='chunk') { $props=['sections'=>$secSch]; $req=['sections']; if($tbl){$props['table']=$tblSch;$req[]='table';} }
else { $props=['title'=>$S,'subtitle'=>$S,'intro'=>$S,'sections'=>$secSch,'conclusion'=>$S,'tip'=>$S,'image_prompts'=>$imgSch]; $req=array_keys($props); if($tbl){$props['table']=$tblSch;$req[]='table';} }
$body = ['contents'=>[['parts'=>[['text'=>$prompt."\nملاحظة: الجمهور في دولة الإمارات، استخدمي الدرهم الإماراتي عند ذكر المال وأمثلة تناسب الإمارات."]]]],
 'generationConfig'=>['responseMimeType'=>'application/json','temperature'=>0.7,'responseSchema'=>['type'=>'OBJECT','properties'=>$props,'required'=>$req]]];
$models = $cfg['models'] ?? ['gemini-3.5-flash-lite','gemini-2.5-flash-lite','gemini-2.5-flash'];
$res = false; $code = 0;
foreach ($KEYS as $KEY) {
 foreach ($models as $model) {
  $url = $cfg['endpoint'] ?? ('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent');
  $ch = curl_init($url);
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>95,CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$KEY],CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE)]);
  $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($res && $code < 400) break 2;
 }
}
if (!$res || in_array($code,[0,429,500,502,503,504])) { @file_put_contents($dir.'/last_err.txt', $code.' '.mb_substr((string)$res,0,600)); out(['ok'=>false,'busy'=>true,'msg'=>'في زحمة على المولد، جربي بعد دقيقة']); }
$fail = function($m=null){ out(['ok'=>false,'msg'=>$m ?: 'ما قدرت أجهز الملف، جربي صياغة ثانية للموضوع']); };
if (!$res || $code >= 400) { @file_put_contents($dir.'/last_err.txt', $code.' '.mb_substr((string)$res,0,600)); $fail('صار ضغط على المولد، جربي مرة ثانية بعد دقيقة'); }
$j = json_decode($res,true); $t='';
foreach (($j['candidates'][0]['content']['parts'] ?? []) as $p) $t .= $p['text'] ?? '';
$d = null; if (preg_match('/\{[\s\S]*\}/u',$t,$m)) $d = json_decode($m[0],true); if (!$d) $d = json_decode($t,true);
if ($d && !empty($d['error'])) out(['ok'=>false,'msg'=>mb_substr((string)$d['error'],0,80)]);
// ===== تجهيز الملف =====
$cl = fn($s)=>trim(preg_replace('/^[\s\-•*·▪\d.)]+/u','',(string)$s));
$tx = fn($s,$n)=>mb_substr(trim(preg_replace('/\s+/u',' ',(string)$s)),0,$n);
$imgp = fn($a)=>array_slice(array_values(array_filter(array_map(fn($s)=>$tx($s,220),is_array($a)?$a:[]),fn($s)=>mb_strlen($s)>5)),0,3);
$oneTip = function($s){ $p = preg_split('/(?<=[.!؟])\s/u',trim((string)$s)); return mb_substr($p[0] ?? '',0,160); };
if ($mode==='outline') {
  if (!$d || empty($d['title']) || empty($d['chapters']) || !is_array($d['chapters'])) $fail();
  $chs = array_slice(array_values(array_filter(array_map(fn($c)=>trim((string)$c),$d['chapters']))),0,6);
  out(['ok'=>true,'outline'=>['title'=>$tx($d['title'],150),'subtitle'=>$tx($d['subtitle']??'',200),'intro'=>$tx($d['intro']??'',500),'conclusion'=>$tx($d['conclusion']??'',500),'tip'=>$oneTip($d['tip']??''),'image_prompts'=>$imgp($d['image_prompts']??[]),'chapters'=>$chs]]);
}
$rowsOf = function($t){ $r=[]; foreach(($t['rows']??[]) as $x){ if (is_array($x) && isset($x['cells']) && is_array($x['cells'])) $r[]=$x['cells']; elseif (is_array($x)) $r[]=array_values($x); } return $r; };
$hasTable = $d && !empty($d['table']['columns']) && is_array($d['table']['columns']) && count($rowsOf($d['table']));
if ($tbl && !$hasTable) $fail('ما طلع الجدول، اضغطي الزر مرة ثانية');
$sections=[];
foreach (array_slice(($d['sections'] ?? []),0,24) as $s) {
  $its = array_slice(array_values(array_filter(array_map($cl,$s['items']??[]))),0,7);
  $hd = $cl($s['heading']??'');
  if ($hd && $its) $sections[] = ['heading'=>$hd,'subheading'=>$tx($s['subheading']??'',160),'body'=>$tx($s['body']??'',450),'icon'=>in_array($s['icon']??'',$ICONS)?$s['icon']:'star','checklist'=>!empty($s['checklist']),'items'=>$its];
}
if (!$d || (!$sections && !$hasTable)) $fail();
$table = $hasTable ? ['columns'=>array_slice(array_map($cl,$d['table']['columns']),0,5),'rows'=>array_map(fn($r)=>array_slice(array_map($cl,$r),0,5),array_slice($rowsOf($d['table']),0,80))] : null;
if ($mode==='chunk') out(['ok'=>true,'part'=>['sections'=>$sections,'table'=>$table]]);
if (empty($d['title'])) $fail();
$doc = ['title'=>$tx($d['title'],150),'subtitle'=>$tx($d['subtitle']??'',200),'intro'=>$tx($d['intro']??'',500),'sections'=>$sections,'conclusion'=>$tx($d['conclusion']??'',500),'tip'=>$oneTip($d['tip']??''),'image_prompts'=>$imgp($d['image_prompts']??[]),'pages'=>$pages,'type'=>$type];
if ($table) $doc['table']=$table;
out(['ok'=>true,'doc'=>$doc]);
