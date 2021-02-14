<?php
function weather($bot, $event, $location) {

  replyTextMessage($bot, $event->getReplyToken(), "【デバッグ１】" . $location);

  $crawler = $client->request('GET', 'http://www.data.jma.go.jp/developer/xml/feed/regular.xml');

  //要素の取得
  $retvalue;
  $tr = $crawler->filter('author name')->each(function($element){
      $retvalue = $retvalue.$element->text()."\n";
  });

  replyTextMessage($bot, $event->getReplyToken(), "【デバッグ２】" . $retvalue);

}

function weather_debug($bot, $event, $location) {

  // 住所ID用変数
  $locationId;
  // XMLファイルをパースするクラス
  $client = new Goutte\Client();
  // XMLファイルを取得
  //$crawler = $client->request('GET', 'http://weather.livedoor.com/forecast/rss/primary_area.xml');
  $crawler = $client->request('GET', 'http://www.data.jma.go.jp/developer/xml/feed/regular.xml');
  replyTextMessage($bot, $event->getReplyToken(), "【デバッグ３】" . $location);
  // 市名のみを抽出しユーザーが入力した市名と比較
  foreach ($crawler->filter('channel ldWeather|source pref city') as $city) {
    // 一致すれば住所IDを取得し処理を抜ける
    if($city->getAttribute('title') == $location || $city->getAttribute('title') . "市" == $location) {
      $locationId = $city->getAttribute('id');
      //replyTextMessage($bot, $event->getReplyToken(), $locationId);
      break;
    }
  }

  // 一致するものが無ければ
  if(empty($locationId)) {
    // 位置情報が送られた時は県名を取得済みなのでそれを代入
    if ($event instanceof \LINE\LINEBot\Event\MessageEvent\LocationMessage) {
      $location = $prefName;
    }
    // 候補の配列
    $suggestArray = array();
    // 県名を抽出しユーザーが入力した県名と比較
    foreach ($crawler->filter('channel ldWeather|source pref') as $pref) {
      // 一致すれば
      if(strpos($pref->getAttribute('title'), $location) !== false) {
        // その県に属する市を配列に追加
        foreach($pref->childNodes as $child) {
          if($child instanceof DOMElement && $child->nodeName == 'city') {
            array_push($suggestArray, $child->getAttribute('title'));
          }
        }
        break;
      }
    }
    // 候補が存在する場合
    if(count($suggestArray) > 0) {
      // アクションの配列
      $actionArray = array();
      //候補を全てアクションにして追加
      foreach($suggestArray as $city) {
        array_push($actionArray, new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder ($city, $city));
      }
      // Buttonsテンプレートを返信
      $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder(
        '見つかりませんでした。',
        new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder ('見つかりませんでした。', 'もしかして？', null, $actionArray));
        $bot->replyMessage($event->getReplyToken(), $builder
      );
    }
    // 候補が存在しない場合
    else {
      // 正しい入力方法を返信
      replyTextMessage($bot, $event->getReplyToken(), '入力された地名が見つかりませんでした。市を入力してください。');
    }
    // 以降の処理はスキップ
    continue;
  }

    // 住所IDが取得できた場合、その住所の天気情報を取得
  $jsonString = file_get_contents('http://weather.livedoor.com/forecast/webservice/json/v1?city=' . $locationId);
  // 文字列を連想配列に変換
  $json = json_decode($jsonString, true);

  // 形式を指定して天気の更新時刻をパース
  $date = date_parse_from_format('Y-m-d\TH:i:sP', $json['description']['publicTime']);

  // 予報が晴れの場合
  if($json['forecasts'][0]['telop'] == '晴れ') {
    // 天気情報、更新時刻、晴れのスタンプをまとめて送信
    replyMultiMessage($bot, $event->getReplyToken(),
      new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($json['description']['text'] . PHP_EOL . PHP_EOL .
        '最終更新：' . sprintf('%s月%s日%s時%s分', $date['month'], $date['day'], $date['hour'], $date['minute'])),
      new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(2, 513)
    );
  // 雨の場合
  } else if($json['forecasts'][0]['telop'] == '雨') {
    replyMultiMessage($bot, $event->getReplyToken(),
      // 天気情報、更新時刻、雨のスタンプをまとめて送信
      new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($json['description']['text'] . PHP_EOL . PHP_EOL .
        '最終更新：' . sprintf('%s月%s日%s時%s分', $date['month'], $date['day'], $date['hour'], $date['minute'])),
      new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(2, 507)
    );
  // 他
  } else {
    // 天気情報と更新時刻をまとめて返信
    //replyTextMessage($bot, $event->getReplyToken(), $json['description']['text'] . PHP_EOL . PHP_EOL .
    //  '最終更新：' . sprintf('%s月%s日%s時%s分', $date['month'], $date['day'], $date['hour'], $date['minute']));
    replyMultiMessage($bot, $event->getReplyToken(),
      // 天気情報、更新時刻、雨のスタンプをまとめて送信
      new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($json['description']['text'] . PHP_EOL . PHP_EOL .
        '最終更新：' . sprintf('%s月%s日%s時%s分', $date['month'], $date['day'], $date['hour'], $date['minute'])),
      new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(2, 512)
    );
  }
}

?>
