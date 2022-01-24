<?php
class Dir
{
    /**
     * 扫描目录下所有文件,
     * @var array
     */
    protected static $files = [];

    public static $ret = [];

    /**
     * 扫描目录路径
     * @param $path string 带扫描的路径
     * @param array $options 要附加的选项,后面可以根据自己需求扩展
     * @return array
     * @author: Vencenty
     */
    static function scan($path, $options = [])
    {
        $options = array_merge([
            'callback'  => null, // 对查找到的文件进行操作
            'filterExt' => [], // 要过滤的文件后缀
        ], $options);

        $scanQueue = [$path];

        while (count($scanQueue) != 0) {
            $rootPath = array_pop($scanQueue);
            $backupDir = str_replace($options['rootPath'], $options['backupPath'], $rootPath);

            // 过滤['.', '..']目录
            $paths = array_filter(scandir($rootPath), function ($path) {
                return !in_array($path, ['.', '..']);
            });

            foreach ($paths as $path) {
                // 拼接完整路径
                $fullPath = $rootPath . DIRECTORY_SEPARATOR . $path;
                // 如果是目录的话,合并到扫描队列中继续进行扫描
                if (is_dir($fullPath)) {
                    self::scan($fullPath, $options);
                }

                // 过滤后缀
                if (!empty($options['filterExt'])) {
                    $pathInfo = pathinfo($fullPath);
                    $ext = $pathInfo['extension'] ?? null;
                    if (!in_array($ext, $options['filterExt'])) {
                        continue;
                    }
                }
                $req_body = ['file'=> new CURLFILE($fullPath)];
                $modres = self::curl('http://api.moderatecontent.com/moderate/', $req_body, 'POST');
                $modres = json_decode($modres);
                if (isset($modres->error_code) && $modres->error_code == 0) {
                    $predictions = round($modres->predictions->everyone, 6);
                    echo 'file: '.$fullPath.' => '.$predictions;
                    if ($predictions > $options['prediction']) {
                        if (!file_exists($backupDir)) {
                            mkdir($backupDir, 0777, true);
                        }
                        $backupPath = $backupDir.'/'.basename($fullPath);
                        rename($fullPath, $backupPath);
                        echo ' moved';

                    }
                    echo PHP_EOL;

                }
                array_push(static::$files, $fullPath);
            }
        }

        return static::$files;
    }


    function curl($url, $data = "", $method = "GET", $header = "", $size = "")
    {
 
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch,CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_ENCODING, 'gzip');
        if (!empty($header)) curl_setopt($ch,CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
        $resultdata = curl_exec($ch);

        $httpheader = curl_getinfo($ch);
        curl_close($ch);
        return $resultdata;
    }


}

$config = yaml_parse_file("config.yml");
if (!$config or !isset($config['dir']['upload']) or !isset($config['dir']['backup'])) exit;

$path = realpath($config['dir']['upload']);
$r = Dir::scan($path, [
    'callback' => function ($file) {
        return $file; 
    },
    'filterExt' => ['jpg', 'jpeg', 'png'],
    'prediction' => $config['prediction'] ?? 0.5,
    'rootPath' => $path,
    'backupPath' => realpath($config['dir']['backup'])
]);
print_r($r);

