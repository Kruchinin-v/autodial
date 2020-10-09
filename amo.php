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

if (isset($_GET['stat'])) {
    $stat = $_GET['stat'];
}

//$id_lead = '29195074';

# !!!!!!!!!!
# в эту переменную вписать действуйщий ключ API
# !!!!!!!!!!

require('/var/www/html/amocrm/tokens/access_token.php');

//$user_hash = 'a6952b1cb4dae7dcbaf37cf2b49903c999488691';

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

$result = mysqli_query($link, $query) or die("Ошибка " . mysqli_error($link));

if(!$result) {
    echo "<p>Выполнение запроса прошло не успешно</p>\n\n";
}

# получили внутренний номер пользователя
$phone_user =mysqli_fetch_array($result)[0];

# запуск функции из asteriskModule.php
$a = calling($phone_user, $phones[0], $stat);
