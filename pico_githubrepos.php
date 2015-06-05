<?php
/**
 * Pico Github Repository List
 * Githubのリポジトリリストをpages配列に追加するプラグイン
 *
 * @author TakamiChie
 * @link http://onpu-tamago.net/
 * @license http://opensource.org/licenses/MIT
 * @version 1.0
 */
class Pico_GithubRepos {

  public function config_loaded(&$settings) {
    $gUser = $settings['github']['username'];
    $dir = $settings['github']['directory'];
    $base_url = $settings['base_url'];
    $cdir = ROOT_DIR . $settings["content_dir"] . $dir;
    $cachedir = CACHE_DIR . "githubrepos/";
    $cachefile = $cachedir . "repos.json";
    if(!file_exists($cachedir)){
      mkdir($cachedir, "0500", true);
    }
		$repos_json = sprintf("https://api.github.com/users/%s/repos?sort=updated", $gUser);

    if(file_exists($cachefile)){
      $filetime = new DateTime();
      $filetime->setTimestamp(filemtime($cachefile));
      $filetime->modify("+1 hour");
      $now = new DateTime();
      if($filetime > $now){
        // キャッシュ有効時は、読み取り処理自体が不要なためスキップ
        return;
      }
    }else{
      // キャッシュ無効なため、以前作成したファイルを全削除
	    if($handle = opendir($cdir)){
        while(false !== ($file = readdir($handle))){
          if($file != "index.md"){
            unlink($cdir. "/" . $file);
          }
        }
        closedir($handle);
	    }
    }
    /* テキストファイル作成処理 */
    try{
      // まずはJSON読み込み
      $content = $this->curl_getcontents($repos_json);
      if($content){
        file_put_contents($cachefile, $content);
      }else{
        throw new Exception(curl_error($ch));
      }
      $json = json_decode($content, true);
      foreach($json as $j){
        // readme読み込み？(失敗したらしたで問題なし)
        $readme = $this->curl_getcontents("https://raw.githubusercontent.com/" . $j["full_name"] . "/master/README.md");
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
    curl_close($ch);
    return $content;
  }
}

?>
