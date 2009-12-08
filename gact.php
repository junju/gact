<?php
session_start();
//require_once 'Zend/Loader.php';
require_once './Zend/Loader/Autoloader.php';
// Zend Frameworkのクラス自動読込
Zend_Loader_Autoloader::getInstance();

	// 同日の0時からの時間をUNIXタイプスタンプで返す
	function splitTime($date) {
		$date = getdate($date); 
		$datetemp = mktime(0,0,0,$date[mon],$date[mday],$date[year]);
		$date = mktime($date[hours],$date[minutes],$date[seconds],$date[mon],$date[mday],$date[year]);
		//echo($datetemp);
  		return $date - $datetemp;
	}
	//同日の任意の時間をセットする
	function changeTime($date, $H, $i) {
		$date = getdate($date);
		$datetemp = mktime($H,$i,0,$date[mon],$date[mday],$date[year]);
  		return $datetemp;
	}
	
// メイン処理
if (!isset($_SESSION['cal_token'])) {
    if (isset($_GET['token'])) {
        // 認証後リダイレクトされたときはここに
        // single-use トークンをセッショントークンに変換
        $session_token = Zend_Gdata_AuthSub::getAuthSubSessionToken($_GET['token']);
        // セッショントークンをセッションに保存
        $_SESSION['cal_token'] = $session_token;


    } else {
        // 初回アクセス時はここになる
        $uriRedirect = 'http://'. $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        //echo $uriRedirect;
        $uriCalendar = 'http://www.google.com/calendar/feeds/';
        $hostedDomain = 'nexchie.com';
        $uriGoogleAuth = Zend_Gdata_AuthSub::getAuthSubTokenUri(
            $uriRedirect,
            $uriCalendar,
            0,1);
            header("Location: ".$uriGoogleAuth."&hd=nexchie.com");
            //echo $uriGoogleAuth;
        echo "<h1>Googleカレンダータイムシート</h1>";
        echo "<a href='$uriGoogleAuth'>ログイン</a>";
    }
}

if (isset($_SESSION['cal_token'])) {

    // 認証済みHTTPクライアント作成
    $client = Zend_Gdata_AuthSub::getHttpClient($_SESSION['cal_token']);

    // Calendarサービスのインスタンス作成
    $service = new Zend_Gdata_Calendar($client);

$query = $service->newEventQuery();
$query->setUser('default');
// MagicCookie 認証の場合は
// $query->setVisibility('private-magicCookieValue') とします
$query->setVisibility('private');
$query->setProjection('full');
$query->setOrderby('starttime');
$query->setFutureevents('false');

$query->setStartMin($_GET[s]);
$query->setStartMax($_GET[e]);

//$query->setStartMin('2009-05-01');
//$query->setStartMax('2009-06-01');
$query->setmaxResults(999);

// カレンダーサーバからイベントの一覧を取得します
try {
    $eventFeed = $service->getCalendarEventFeed($query);
} catch (Zend_Gdata_App_Exception $e) {
    echo "エラー: " . $e->getMessage();
}
echo "<h1>" . $eventFeed->title. "</h1>";
echo $eventFeed->totalResults . "event(s) found.<p/>";
echo "<a href ='./gact.php'>再ログイン</a>";

// リストの内容を順に取得し、HTML のリストとして出力します
echo "<table>";
echo "<tr><th>プロジェクト</th><th>タスク</th><th>日付</th><th>時間帯A</th><th>時間帯B</th><th>分割警告</th><th>開始時刻</th><th>終了時刻</th>";
echo "</tr>";
foreach ($eventFeed as $event) {

	$starttemp = strtotime($event->when[0]->startTime);
    $endtemp = strtotime($event->when[0]->endTime);
	$startdate = date('Y/m/d', strtotime($event->when[0]->startTime));
    $startTime = date('H:i', strtotime($event->when[0]->startTime));
    $enddate = date('Y/m/d', strtotime($event->when[0]->endTime));
    $endTime = date('H:i', strtotime($event->when[0]->endTime));
    $title = explode("/",$event->title);
    $project = $title[0];
    $task = $title[1];
    $eventId = $event->id;
    $author = $event->content;
    
 /*
プロジェクト、タスク、日付、時間帯1（0:00ー5:00；５時間）, 時間帯２（5:00-22:00；17時間）,時間帯３（22:00-24:00；2時間）

時間帯３から翌日の時間帯１以降にまたがるものをどう計算するか⇒運用側で分割する

開始時刻＜= 5:00 ならば、時間帯１＝終了時刻ー開始時刻、時間帯１が５を超えたら、開始時刻を5:00に直して次へ
開始時刻＞＝5:00 and 開始時刻＜＝22:00 ならば、時間帯２＝終了時刻ー開始時刻、時間帯２が１７を超えたら、開始時刻を22:00に直して次へ
開始時刻＞＝22:00and 開始時刻＜＝24:00 ならば、時間帯３＝終了時刻ー開始時刻、時間帯３が２を超えたら、分割警告

時間帯A ＝時間帯２
時間帯B ＝時間帯１＋３

*/
	
		$duration1 =0;
		$duration2 =0;
		$duration3 =0;
		$caution ="";

		//echo(splitTime($starttemp));
		if (splitTime($starttemp) <= 5*60*60 ){
			//echo("duration1");
			$duration1 = ( $endtemp - $starttemp ) /(60*60);
			//echo($duration1);
			if( $duration1 > 5 ){
				$duration1 = 5;
				$starttemp = changeTime($starttemp,5,0);
				//console.log(starttemp);
			}
		}

		if ((splitTime($starttemp) >= 5*60*60) && (splitTime($starttemp) <= 22*60*60)){
				//echo("duration2");
				$duration2 = ( $endtemp - $starttemp ) /(60*60);
				//echo($duration2);
				if( $duration2 > 17){
					$duration2 = 17;
					$starttemp = changeTime($starttemp,22,0);
				}
		}

		if ((splitTime($starttemp) >= 22*60*60) && (splitTime($starttemp) <= 24*60*60)){
				//echo("duration3");
				$duration3 = ( $endtemp - $starttemp ) /(60*60);
				//echo($duration3);
				if( $duration3 > 2){
					$duration2 = 2;
					$caution = "caution";
				}
		}

		$durationA = $duration2;
		$durationB = $duration1 + $duration3;


    
    echo "<tr><td>" . $project . "</td><td>". $task ."</td><td>".$startdate."</td><td>". $durationA ."</td><td>".$durationB."</td><td>".$caution."</td><td>".$startTime."</td><td>".$endTime."</td></tr>";
	}
echo "</table>";

}
?>