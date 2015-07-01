<?php
/**
 * Pico Github Repository List
 * Githubのリポジトリリストをpages配列に追加する自動更新モジュール
 *
 * @author TakamiChie
 * @link http://onpu-tamago.net/
 * @license http://opensource.org/licenses/MIT
 * @version 1.0
 */
class Pico_GithubRepos {

  public function run($settings) {
    if(empty($settings['github']['username']) ||
      empty($settings['github']['directory'])){
      return;
    }
    $gUser = $settings['github']['username'];
    $dir = $settings['github']['directory'];
    $cdir = ROOT_DIR . $settings["content_dir"] . $dir;
    $cachedir = LOG_DIR . "githubrepos/";
    $cachefile = $cachedir . "repos.json";
    if(!file_exists($cachedir)){
      mkdir($cachedir, "0500", true);
    }
		$repos_json = sprintf("https://api.github.com/users/%s/repos?sort=updated", $gUser);

    // 以前作成したファイルを全削除
    if($handle = opendir($cdir)){
      while(false !== ($file = readdir($handle))){
        if(!is_dir($file) && $file != "index.md"){
          unlink($cdir. "/" . $file);
        }
      }
      closedir($handle);
    }
    /* テキストファイル作成処理 */
    try{
      // まずはJSON読み込み
      $content = $this->curl_getcontents($repos_json);
      file_put_contents($cachefile, $content);
      $json = json_decode($content, true);
      foreach($json as $j){
        // readme読み込み？(失敗したらしたで問題なし)
        $readme = $this->curl_getcontents("https://raw.githubusercontent.com/" . $j["full_name"] . "/master/README.md");
        if($readme == "Not Found"){
          $readme = ""; // Not Foundが帰ってきたら、ファイルはなかったものとみなす
        }
        // mdファイル作成
        $t = array();
        if($j["fork"]) array_push($t, "fork");
        if($j["has_downloads"]) array_push($t, "downloadable");
        if($j["language"]) array_push($t, $j["language"]);
        $page = "/*\n";
        $page .= sprintf("  Title: %s\n", $j["name"]);
        $page .= sprintf("  Author: %s\n", $j["owner"]["login"]);
        $page .= sprintf("  Date: %s\n", $j["pushed_at"]);
        $page .= sprintf("  Description: %s\n", $j["description"]);
        $page .= sprintf("  URL: %s\n", $j["html_url"]);
        $page .= sprintf("  Tag: %s\n", implode(", ", $t));
        $page .= "*/\n";
        $page .= $readme ? $readme : $j["description"];

        file_put_contents($cdir . $j["name"] . ".md", $page);
      }
    }catch(Exception $e){
      $page = "/*\n";
      $page .= sprintf("  Title: %s\n", "Github Access Error");
      $page .= sprintf("  Description: %s\n", "Github Access Error");
      $page .= "*/\n";
      $page .= "Githubに接続できませんでした。\n";
      $page .= $e->getMessage();
      file_put_contents($cdir . "error.md", $page);
    }
	}
  
  private function curl_getcontents($url)
  {
    $ch = curl_init();
    curl_setopt_array($ch, array(
      CURLOPT_URL => $url,
      CURLOPT_TIMEOUT => 3,
    	CURLOPT_CUSTOMREQUEST => 'GET',
    	CURLOPT_SSL_VERIFYPEER => FALSE,
    	CURLOPT_RETURNTRANSFER => TRUE,
    	CURLOPT_USERAGENT => "Pico"));

    $content = curl_exec($ch);
    if(!$content){
      throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    return $content;
  }
}

?>
