<?php

include 'functions/getContact.php';
include 'functions/getPhones.php';
include 'functions/asteriskModule.php';

if (isset($_POST['leads']['status'][0]['id'])) {
    $id_lead = $_POST['leads']['status'][0]['id'];
}
else {
    echo "Нет необходимых данных\n";
    return 0;
}

$stat = 0;
$type_dial = '';
$phone = 0;

if (isset($_GET['stat'])) {
    $stat = $_GET['stat'];
}

//$id_lead = '29195074';
//$id_lead = '31407388';

if (isset($_GET['type'])) {
    $type_dial = $_GET['type'];
}

if (isset($_GET['phone'])) {
    $phone = $_GET['phone'];
}

$dateN = date(DATE_RFC822);
$path_log = "/var/www/html/amocrm/autodial/log-simple.log";
$current = file_get_contents($path_log);
$current .= "\n~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n";
$current .= "\n". $dateN . " GET:\n" . var_export($_GET,true) . "\n";
$current .= "\n". $dateN . " POST:\n" . var_export($_POST,true) . "\n";
$current .= "\n~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n";
file_put_contents($path_log, $current);

require('/var/www/html/amocrm/tokens/access_token.php');

# получение id контакта и ответственного, для получения их номеров телефонов
list ($id_contact, $id_user) = getContact($id_lead, $access_token);

# получение номеров телефонов контакта
$phones = getPhones($id_contact, $access_token);

/**
 * Подключение к базе данных
 */
require_once 'functions/connection.php'; // подключаем скрипт

# подключение к  базе
$link = mysqli_connect($host, $user, $password, $database)
    or die("Ошибка " . mysqli_error($link));

$query = "select id from sip where data=" . $id_user;
$result = mysqli_query($link, $query) or die("\nОшибка " . mysqli_error($link) . "\n");
if(!$result) {
    echo "<p>Выполнение запроса прошло не успешно</p>\n\n";
}

# получили внутренний номер пользователя
$phone_user =mysqli_fetch_array($result)[0];

//$current .= "\n". var_export($phone_user,true) . "\n";
//$current .= "\n". var_export($phones[0],true) . "\n";


# запуск функции из asteriskModule.php
$a = calling($phone_user, $phones[0], $type_dial, $phone);
