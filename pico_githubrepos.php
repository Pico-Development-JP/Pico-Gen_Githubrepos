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
    $orgs = $settings['github']['organizations'];
    $cdir = $settings["content_dir"] . $dir;
    $cachedir = LOG_DIR . "githubrepos/";
    if(!file_exists($cdir)){
      mkdir($cdir, "0500", true);
    }
    if(!file_exists($cachedir)){
      mkdir($cachedir, "0500", true);
    }
    $this->removeBeforeScanned($cdir);
    echo ">> Personal Document(s)\n";
		$repos_json = sprintf("https://api.github.com/users/%s/repos?sort=updated", $gUser);
    $this->create_reposfile($repos_json, $cdir, $cachedir . "repos.json");
    if($orgs) {
      echo ">> Organization Document(s)\n";
      foreach ($orgs as $oneorg) {
        echo sprintf(">  %s Document(s)\n", $oneorg['name']);
        $cdir = $settings["content_dir"] . $oneorg['directory'];
        if(!file_exists($cdir)){
          mkdir($cdir, "0500", true);
        }
        $this->removeBeforeScanned($cdir);
        $repos_json = sprintf("https://api.github.com/orgs/%s/repos?sort=updated", $oneorg['name']);
        $this->create_reposfile($repos_json, $cdir, $cachedir . sprintf("%s_repos.json", $oneorg['name']));
      }
    }
	}

  private function create_reposfile(string $url, string $contentdir, string $cachefile){
    try{
      $responce;
      // まずはJSON読み込み
      $content = $this->curl_getcontents($url, $responce);
      file_put_contents($cachefile, $content);
      $json = json_decode($content, true);
      if($responce['http_code'] >= 300){
        throw new Exception($json["message"]);
      }
      foreach($json as $j){
        // readme読み込み？(失敗したらしたで問題なし)
        $readme = $this->curl_getcontents("https://raw.githubusercontent.com/" . $j["full_name"] . "/" . $j["default_branch"] . "/README.md");
        if($readme == "Not Found"){
          $readme = ""; // Not Foundが帰ってきたら、ファイルはなかったものとみなす
        }
        // mdファイル作成
        $t = array();
        if($j["fork"]) array_push($t, "fork");
        if($j["has_downloads"]) array_push($t, "downloadable");
        if($j["language"]) array_push($t, $j["language"]);
        $page = "---\n";
        $page .= sprintf("Title: %s\n", $j["name"]);
        $page .= sprintf("Author: %s\n", $j["owner"]["login"]);
        $page .= sprintf("Date: %s\n", $j["pushed_at"]);
        $page .= sprintf("Description: %s\n", $j["description"]);
        $page .= sprintf("URL: %s\n", $j["html_url"]);
        $page .= sprintf("Tag: %s\n", implode(", ", $t));
        $page .= "---\n";
        $page .= $readme ? $readme : $j["description"];

        $fn = $j["name"] . ".md";
        echo $fn . " Save Success\n";
        file_put_contents($contentdir . $fn, $page);
      }
    }catch(Exception $e){
      echo "Github Access Error\n";
      echo $e->getMessage();
    }
  }

  /**
   *
   * ファイルをダウンロードする
   *
   * @param string $url URL
   * @param array $responce レスポンスヘッダが格納される配列(参照渡し)。省略可能
   *
   */
  private function curl_getcontents($url, &$responce = array())
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
    if(!curl_errno($ch)) {
      $responce = curl_getinfo($ch);
    } 
    if(!$content){
      throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    return $content;
  }

  /**
   *
   * 以前自動生成した原稿ファイルを全削除する
   *
   * @param string $cdir 対象のファイルが格納されているディレクトリパス
   *
   */
  private function removeBeforeScanned($cdir){
    if($handle = opendir($cdir)){
      while(false !== ($file = readdir($handle))){
        if(!is_dir($file) && $file != "index.md"){
          unlink($cdir. "/" . $file);
        }
      }
      closedir($handle);
    }
  }
}

?>
