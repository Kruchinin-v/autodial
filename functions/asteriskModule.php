<?php

ini_set('display_errors',0);
define('AC_HOST','localhost');
//define('AC_PORT',8088);
define('AC_PORT',5038);
//define('AC_PREFIX','/asterisk/');
define('AC_TLS',false);
define('AC_DB_CS','mysql:host=localhost;port=3306;dbname=asteriskcdrdb');
define('AC_DB_UNAME','root');
define('AC_DB_UPASS','');
define('AC_TIMEOUT',0.75);
define('AC_RECORD_PATH','https://ats.karnavalnn.ru/monitor/%Y/%m/%d/#');
define('AC_TIME_DELTA',3); // hours. Ex. GMT+3 = 4

$db_cs=AC_DB_CS;
$db_u=!strlen(AC_DB_UNAME)?NULL:AC_DB_UNAME;
$db_p=!strlen(AC_DB_UPASS)?NULL:AC_DB_UPASS;
date_default_timezone_set('UTC');



function calling($fromPhone, $toPhone, $stat = 0) {

    if (AC_PORT<1) die('Please, configure settings first!'); // die if not

    $login = "amocrm";
    $secret = "W7Xp6loW";

    # строка для подключения к ami
    $loginArr=array(
        'Action'=>'Login',
        'username'=>$login,
        'secret'=>$secret,
//	    'Events'=>'off',
    );

    global $ans;
    $ans = [];

    /** MakeRequest to asterisk interfacees
     * @param $params -- array of req. params
     * @return array -- response
     */
    function asterisk_req($params,$quick=false){
        // lets decide if use AJAM or AMI
        return !defined('AC_PREFIX')?ami_req($params,$quick):ajam_req($params);
    }

    /** Echo`s data
     * @param $array answer data
     * @return array -- response
     */
    function answer($array){
        global $ans;
//        header('Content-type: text/javascript;');
//        echo json_encode($array) . "\n";
        $ans[] = json_encode($array) . "\n";
//        echo var_export($ans,true);
    }

    /*** Reads data from coinnection
     * @param $connection -- active connection
     * @param bool $quick -- should we wait for timeout or return an answer after getting command status
     * @return string RAW response
     */
    function ami_read($connection,$quick=false){
        $str='';
        do {
            $line = fgets($connection, 4096);
            $str .= $line;
            $info = stream_get_meta_data($connection);
            if ($quick and $line== "\r\n") break;
        }while ($info['timed_out'] == false );
        echo "~\n AMI:  " . var_export($str,true) . "~\n";
        return $str;
    }

    /*** Make request with AMI
     * @param $params -- array of req. params
     * @param bool $quick -- if we need more than action result
     * @return array result of req
     */
    function ami_req($params,$quick=false){
        static $connection;
        $action = '';
        if ($params===NULL and $connection!==NULL) {
            // close connection
            fclose($connection);
            return;
        }
        if ($connection===NULL){
            $en=$es='';
            $connection = fsockopen(AC_HOST, AC_PORT, $en, $es, 3);
            // trying to connect. Return an error on fail
            if ($connection) register_shutdown_function('asterisk_socket_shutdown');
            else {$connection=NULL; return array(0=>array('response'=>'error','message'=>'socket_err:'.$en.'/'.$es));}
        }

        # определение, что выполняется команда - originate
        if ($params['Action'] == 'Originate') {
            $action = 'call';
//            echo "action: " . $action . "\n";


        }

        // building req.
//        echo "\nstart\n";

        $str=array();
        foreach($params as $k=>$v) $str[]="{$k}: {$v}";
        $str[]='';
        $str=implode("\r\n",$str);

        if ($action == 'call') {
//            echo var_export($str,true);
            # запись в файл. нужно для тестов
            $file = '/var/www/html/amocrm/ans.json';
            file_put_contents($file, var_export($params,true));
        }

        // writing
        fwrite($connection,$str."\r\n");
        // Setting stream timeout
        $seconds=ceil(AC_TIMEOUT);
        $ms=round((AC_TIMEOUT-$seconds)*1000000);
        stream_set_timeout($connection,$seconds,$ms);
        // reading respomse and parsing it
        $str= ami_read($connection,$quick);
        $r=rawman_parse($str);

        if ($action == 'call') {
           file_put_contents($file, var_export($str,true));
        }


//        echo "\nend\n";

        return $r;
    }

    /**
     * Shudown function. Gently close the socket
     */
    function asterisk_socket_shutdown(){ami_req(NULL);}

    /** Parse RAW response
     * @param $lines RAW response
     * @return array parsed response
     */
    function rawman_parse($lines){
        $lines=explode("\n",$lines);
        $messages=array();
        $message=array();

        foreach ($lines as $l){
            $l=trim($l);
            if (empty($l) and count($message)>0){ $messages[]= $message;  $message=array(); continue;}
            if (empty($l))  continue;
            if (strpos($l,':')===false)  continue;
            list($k,$v)=explode(':',$l);
            $k=strtolower(trim($k));
            $v=trim($v);
            if (!isset( $message[$k]))  $message[$k]=$v;
            elseif (!is_array( $message[$k]))  $message[$k]=array( $message[$k],$v);
            else  $message[$k][]=$v;
        }
        if (count($message)>0) $messages[]= $message;
        return $messages;
    }



    $resp=asterisk_req($loginArr,true);

    // problems? exiting
    if ($resp[0]['response']!=='Success') answer(array('status'=>'error','data'=>$resp[0]));
//~~~~~~~~~~~~~~~~
    $file = '/tmp/ans.txt';
    file_put_contents($file, var_export($stat,true));
//~~~~~~~~~~~~~~~~
    if ($stat != 0) {
        # звонок сначала клиенту
        $params=array(
            'Action'=>'Originate',
            'ActionID'=>'myId',
//            'channel'=>'Local/'.intval($toPhone) . "@from-internal",
            'channel'=>'Local/'.intval($toPhone) . "@amocrm-callthelist-client",
            'Exten'=>strval($fromPhone),
            'Context'=>'amocrm',
            'priority'=>'1',
            'Timeout'=>'160000',
            'Callerid'=>'amocrm <' . $toPhone . '>',
            'Async'=>'Yes',
            // Not Implemented:
            //'Callernumber'=>'150',
            //'CallerIDName'=>'155',
        );
    }
    else {
        # звонок сначала МП
        $params=array(
            'Action'=>'Originate',
            'ActionID'=>'myId',
            'channel'=>'Local/'.intval($fromPhone) . "@amocrm",             # звонить сначала мп
            'Exten'=>strval($toPhone),                                      # звонить сначала мп
//            'Context'=>'from-internal',                                    # звонить сначала мп
            'Context'=>'amocrm-out',                                    # звонить сначала мп
            'priority'=>'1',
            'Timeout'=>'190000',
            'Callerid'=>$toPhone . ' <' . $toPhone . '>',
            'Async'=>'Yes',
            // Not Implemented:
            //'Callernumber'=>'150',
            //'CallerIDName'=>'155',
        );
    }




    $resp=asterisk_req($params,true);
//    if ($resp[0]['response']!=='Success') answer(array('status'=>'error','data'=>$resp[0]));
    answer(array('status'=>'ok','action'=>'call','data'=>$resp[0]));


    return $ans;

}



/*$a = calling(100,79805429794, 1);

$a = var_export($a,true);

echo $a . "\n";*/
