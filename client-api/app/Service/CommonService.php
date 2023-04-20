<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use App\Services\BaseService;

class CommonService extends BaseService
{
    /**
     * @param array | string $data 数据
     * @param int $status 返回状态码
     * @param string $msg 返回信息
     * @param string $escaped 中文编码
     */
    public static function returnData($data, $status = 1, $msg = "success", $escaped = JSON_UNESCAPED_UNICODE)
    {
        if (is_array($data)) {
            $returnData = [
                'status' => $status,
                'msg' => $msg,
            ];
            $returnData = array_merge($returnData, $data);
        } else {
            $returnData = [
                'status' => $status,
                'msg' => $msg,
            ];
        }
        return json_encode($returnData, $escaped);
    }


    /*
  * 基本信息的效验
  */
    public static function check($data, $rule = NULL, $ext = NULL)
    {
        $data = trim(str_replace(PHP_EOL, '', $data));

        if (empty($data)) {
            return false;
        }

        $validate['require'] = '/.+/';
        $validate['url'] = '/^http(s?):\\/\\/(?:[A-za-z0-9-]+\\.)+[A-za-z]{2,4}(?:[\\/\\?#][\\/=\\?%\\-&~`@[\\]\':+!\\.#\\w]*)?$/';
        $validate['currency'] = '/^\\d+(\\.\\d+)?$/';
        $validate['number'] = '/^\\d+$/';
        $validate['zip'] = '/^\\d{6}$/';
        $validate['cny'] = '/^(([1-9]{1}\\d*)|([0]{1}))(\\.(\\d){1,2})?$/';
        $validate['integer'] = '/^[\\+]?\\d+$/';
        $validate['double'] = '/^[\\+]?\\d+(\\.\\d+)?$/';
        $validate['english'] = '/^[A-Za-z]+$/';
        $validate['idcard'] = '/^([0-9A-Za-z]\d{6,}|[0-9]{15}|[0-9]{17}[0-9a-zA-Z])$/';
        $validate['truename'] = '/^[\\x{4e00}-\\x{9fa5}]{2,8}(·?[\\x{4e00}-\\x{9fa5}]{2,8}){0,}$/u';
        $validate['username'] = '/^[a-zA-Z]{1}[0-9a-zA-Z_]{5,15}$/';
        $validate['email'] = '/^\\w+([-+.]\\w+)*@\\w+([-.]\\w+)*\\.\\w+([-.]\\w+)*$/';
        $validate['moble'] = '/^(9[976]\d|8[987530]\d|6[987]\d|5[90]\d|42\d|3[875]\d|
2[98654321]\d|9[8543210]|8[6421]|6[6543210]|5[87654321]|
4[987654310]|3[9643210]|2[70]|7|1)\d{1,14}$/';
        //$validate['password'] = '/^[a-zA-Z0-9_\\@\\#\\$\\%\\^\\&\\*\\(\\)\\!\\,\\.\\?\\-\\+\\|\\=]{6,20}$/';
        $validate['password'] = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z_]{6,16}$/';
        $validate['xnb'] = '/^[a-zA-Z]$/';
        if (isset($validate[strtolower($rule)])) {
            $rule = $validate[strtolower($rule)];
            return preg_match($rule, $data);
        }

        $Ap = '\\x{4e00}-\\x{9fff}' . '0-9a-zA-Z\\@\\#\\$\\%\\^\\&\\*\\(\\)\\!\\,\\.\\?\\-\\+\\|\\=';
        $Cp = '\\x{4e00}-\\x{9fff}';
        $Dp = '0-9';
        $Wp = 'a-zA-Z\s';
        $Np = 'a-z';
        $Tp = '@#$%^&*()-+=';
        $_p = '_';
        $pattern = '/^[';
        $OArr = str_split(strtolower($rule));
        in_array('a', $OArr) && ($pattern .= $Ap);
        in_array('c', $OArr) && ($pattern .= $Cp);
        in_array('d', $OArr) && ($pattern .= $Dp);
        in_array('w', $OArr) && ($pattern .= $Wp);
        in_array('n', $OArr) && ($pattern .= $Np);
        in_array('t', $OArr) && ($pattern .= $Tp);
        in_array('_', $OArr) && ($pattern .= $_p);
        isset($ext) && ($pattern .= $ext);
        $pattern .= ']+$/u';
        return preg_match($pattern, $data);
    }


    //验证身份证是否有效
    public static function validateIDCard($IDCard)
    {
        if (strlen($IDCard) == 18) {
            return self::check18IDCard($IDCard);
        } elseif ((strlen($IDCard) == 15)) {
            $IDCard = self::convertIDCard15to18($IDCard);
            return self::check18IDCard($IDCard);
        } else {
            return false;
        }
    }

//计算身份证的最后一位验证码,根据国家标准GB 11643-1999
    public static function calcIDCardCode($IDCardBody)
    {
        if (strlen($IDCardBody) != 17) {
            return false;
        }
        //加权因子
        $factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
        //校验码对应值
        $code = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
        $checksum = 0;

        for ($i = 0; $i < strlen($IDCardBody); $i++) {
            $checksum += substr($IDCardBody, $i, 1) * $factor[$i];
        }

        return $code[$checksum % 11];
    }

    // 将15位身份证升级到18位
    public static function convertIDCard15to18($IDCard)
    {
        if (strlen($IDCard) != 15) {
            return false;
        } else {
            // 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码
            if (array_search(substr($IDCard, 12, 3), array('996', '997', '998', '999')) !== false) {
                $IDCard = substr($IDCard, 0, 6) . '18' . substr($IDCard, 6, 9);
            } else {
                $IDCard = substr($IDCard, 0, 6) . '19' . substr($IDCard, 6, 9);
            }
        }
        $IDCard = $IDCard . self:: calcIDCardCode($IDCard);
        return $IDCard;
    }

    // 18位身份证校验码有效性检查
    public static function check18IDCard($IDCard)
    {
        if (strlen($IDCard) != 18) {
            return false;
        }

        $IDCardBody = substr($IDCard, 0, 17); //身份证主体
        $IDCardCode = strtoupper(substr($IDCard, 17, 1)); //身份证最后一位的验证码

        if (self:: calcIDCardCode($IDCardBody) != $IDCardCode) {
            return false;
        } else {
            return true;
        }
    }


    //短信接口
    public static function send_moble($moble, $content)
    {
        $data = [
            'text' => $content,
            'apikey' => '36f547551fb02d18573b12b2c7e91fde',
            'mobile' => $moble
        ];


        $ch = curl_init('http://sms.yunpian.com/v2/sms/single_send.json');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        /*curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1');
        curl_setopt($ch, CURLOPT_PROXYPORT, 8888);*/

        $json_data = curl_exec($ch);
        curl_close($ch);

        $array = json_decode($json_data, true);
        if (isset($array['code']) && $array['code'] == '0') {
            return true;
        }
        return '验证码发送失败，请重试';
    }

    /*
     * 邀请码
     */
    public static function tradenoa()
    {
        return substr(str_shuffle('ABCDEFGHIJKLMNPQRSTUVWXYZ'), 0, 6);
    }

    /**
     * 生成随机码
     * @param int $length
     * @return string
     */
    public static function generate_password($length = 64)
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /*
     * 获取客户端IP
     */
    public static function get_client_ip()
    {
        return self::get_ip_address();

        global $ip;
        if (getenv("HTTP_CLIENT_IP"))
            $ip = getenv("HTTP_CLIENT_IP");
        else if (getenv("HTTP_X_FORWARDED_FOR"))
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        else if (getenv("REMOTE_ADDR"))
            $ip = getenv("REMOTE_ADDR");
        else $ip = "Unknow";
        return $ip;
    }

    /*
     * 邀请码
     */
    public static function createOrderId($len = 7)
    {

        $chars = '0829746513';
        $string = '';
        for ($i = 0; $i < $len; $i++) {
            $string .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return '152' . $string;
    }

    /**
     * 导出Excel
     * @param array $title 表头标题， 要与导出的字段一一对应
     * @param array $data 要导出的数据
     * @param string $fileName 文件名
     */
    public static function exportExcel($title, $data, $fileName = "excel-", $type = 'xls')
    {
        $fileName = $fileName . date("Y-m-d-H-i-s");
        array_unshift($data, $title);
        Excel::create($fileName, function ($excel) use ($data) {
            $excel->sheet('score', function ($sheet) use ($data) {
                $sheet->rows($data);
            });
        })->store($type);
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        return $http_type . $_SERVER['HTTP_HOST'] . '/exports/' . $fileName . '.' . $type;

    }

    /**
     * 导出CSV文件
     * @param array $data 数据
     * @param array $header_data 首行数据
     * @param string $file_name 文件名称
     * @return string
     */
    public static function export_csv($header_data = [], $data = [], $file_name = '')
    {
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition: attachment;filename=' . $file_name);
        header('Cache-Control: max-age=0');

        $fp = fopen('php://output', 'a');
        if (!empty($header_data)) {
            foreach ($header_data as $key => $value) {
                $header_data[$key] = iconv('utf-8', 'gbk', $value);
            }
            fputcsv($fp, $header_data);
        }
        $num = 0;
        //每隔$limit行，刷新一下输出buffer，不要太大，也不要太小
        $limit = 100000;
        //逐行取出数据，不浪费内存
        $count = count($data);
        if ($count > 0) {
            for ($i = 0; $i < $count; $i++) {
                $num++;
                //刷新一下输出buffer，防止由于数据过多造成问题
                if ($limit == $num) {
                    ob_flush();
                    flush();
                    $num = 0;
                }
                $row = get_object_vars($data[$i]);
                foreach ($row as $key => $value) {
                    $row[$key] = iconv('utf-8', 'gbk', $value);
                }
                fputcsv($fp, $row);
            }
        }
        fclose($fp);
        return;
    }

    /**
     * 请求接口并返回内容
     * @param  string $url [请求的URL地址]
     * @param  string $params [请求的参数]
     * @param  int $ipost [是否采用POST形式]
     * @return  string
     */
    public static function requestCurl($url)
    {
        //$url = "https://gateio.io/json_svr/query_push";
        $headers = array(
            "u" => 13,
            "type" => 'push_main_rates',
            "symbol" => 'USDT_CNY'
        );

        $context = stream_context_create(array(
            'http' => array(
                "method" => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => http_build_query($headers),
                'timeout' => 20
            )
        ));
        $result = file_get_contents($url, false, $context);
        return $result;
    }

    //短信模板

    /**
     * @param $code int
     * @param $sms_name string
     * @return $content string
     */
    public static function sms_template($code, $sms_name)
    {
        if ($sms_name == 'register')
            return "【MITOP】您的验证码是{$code}请在2分钟内验证，切勿泄露给他人。如非本人操作，请忽略。";
    }


    /**
     * 请求接口返回内容
     * @param  string $url [请求的URL地址]
     * @param  string $params [请求的参数]
     * @param  int $ipost [是否采用POST形式]
     * @return  string
     */
    public static function juhecurl($url, $params = false, $ispost = 0)
    {
        $httpInfo = false;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JuheData');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        if ($ispost) {
            $header = [
                'Authorization:' . base64_encode('home:' . md5('home.' . base64_encode(json_encode($params)) . '.11111')),

            ];
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);
        $response = json_decode($response, true);
        if ($response['error'] !== null) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }

        //$httpCode = curl_getinfo( $ch , CURLINFO_HTTP_CODE );
        //$httpInfo = array_merge( $httpInfo , curl_getinfo( $ch ) );
        curl_close($ch);
        return $response['result'];
    }

    public static function get_ip_address()
    {
        // check for shared internet/ISP IP
        if (!empty($_SERVER['HTTP_CLIENT_IP']) && self::validate_ip($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        // check for IPs passing through proxies
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // check if multiple ips exist in var
            if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {
                $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                foreach ($iplist as $ip) {
                    if (self::validate_ip($ip))
                        return $ip;
                }
            } else {
                if (self::validate_ip($_SERVER['HTTP_X_FORWARDED_FOR']))
                    return $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED']) && self::validate_ip($_SERVER['HTTP_X_FORWARDED']))
            return $_SERVER['HTTP_X_FORWARDED'];
        if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && self::validate_ip($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
            return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && self::validate_ip($_SERVER['HTTP_FORWARDED_FOR']))
            return $_SERVER['HTTP_FORWARDED_FOR'];
        if (!empty($_SERVER['HTTP_FORWARDED']) && self::validate_ip($_SERVER['HTTP_FORWARDED']))
            return $_SERVER['HTTP_FORWARDED'];

        // return unreliable ip since all else failed
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Ensures an ip address is both a valid IP and does not fall within
     * a private network range.
     */
    public static function validate_ip($ip)
    {
        if (strtolower($ip) === 'unknown')
            return false;

        // generate ipv4 network address
        $ip = ip2long($ip);

        // if the ip is set and not equivalent to 255.255.255.255
        if ($ip !== false && $ip !== -1) {
            // make sure to get unsigned long representation of ip
            // due to discrepancies between 32 and 64 bit OSes and
            // signed numbers (ints default to signed in PHP)
            $ip = sprintf('%u', $ip);
            // do private network range checking
            if ($ip >= 0 && $ip <= 50331647) return false;
            if ($ip >= 167772160 && $ip <= 184549375) return false;
            if ($ip >= 2130706432 && $ip <= 2147483647) return false;
            if ($ip >= 2851995648 && $ip <= 2852061183) return false;
            if ($ip >= 2886729728 && $ip <= 2887778303) return false;
            if ($ip >= 3221225984 && $ip <= 3221226239) return false;
            if ($ip >= 3232235520 && $ip <= 3232301055) return false;
            if ($ip >= 4294967040) return false;
        }
        return true;
    }

    //参数1：访问的URL，参数2：post数据(不填则为GET)，参数3：提交的$cookies,参数4：是否返回$cookies
    public static function curl_request($url,$post='',$cookie='', $returnCookie=0){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_REFERER, "http://XXX");
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
        if($post) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if($cookie) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_error($curl);
        }
        curl_close($curl);
        if($returnCookie){
            list($header, $body) = explode("\r\n\r\n", $data, 2);
            preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
            $info['cookie']  = substr($matches[1][0], 1);
            $info['content'] = $body;
            return $info;
        }else{
            return $data;
        }
    }



}