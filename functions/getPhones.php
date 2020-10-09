<?php
function getPhones($id_contact, $access_token) {
    /* Для начала нам необходимо инициализировать данные, необходимые для составления запроса. */
    $subdomain = 'korolevadarya'; #Наш аккаунт - поддомен

    $link = 'https://' . $subdomain . '.amocrm.ru/api/v2/contacts?id=' . $id_contact;

    $headers = [
        'Authorization: Bearer ' . $access_token
    ];

    $curl = curl_init(); #Сохраняем дескриптор сеанса cURL
#Устанавливаем необходимые опции для сеанса cURL
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
    curl_setopt($curl, CURLOPT_URL, $link);
    curl_setopt($curl,CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
    curl_setopt($curl, CURLOPT_COOKIEJAR, dirname(__FILE__) . '/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    $out = curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    /* Вы также можете передать дополнительный HTTP-заголовок IF-MODIFIED-SINCE, в котором указывается дата в формате D, d M Y
    H:i:s. При
    передаче этого заголовка будут возвращены контакты, изменённые позже этой даты. */
//curl_setopt($curl, CURLOPT_HTTPHEADER, array('IF-MODIFIED-SINCE: Mon, 01 Aug 2013 07:07:23'));
    /* Теперь мы можем обработать ответ, полученный от сервера. Это пример. Вы можете обработать данные своим способом. */
    $code = (int)$code;
    $errors = array(
        301 => 'Moved permanently',
        400 => 'Bad request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not found',
        500 => 'Internal server error',
        502 => 'Bad gateway',
        503 => 'Service unavailable',
    );
    try {
        #Если код ответа не равен 200 или 204 - возвращаем сообщение об ошибке
        if ($code != 200 && $code != 204) {
            throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error', $code);
        }
    } catch (Exception $E) {
        die('Ошибка: ' . $E->getMessage() . PHP_EOL . 'Код ошибки: ' . $E->getCode(). "\n");
    }
    /*
    Данные получаем в формате JSON, поэтому, для получения читаемых данных,
    нам придётся перевести ответ в формат, понятный PHP
     */
    $Response = json_decode($out, true);

    $Response = $Response['_embedded']['items'];
    $Response = $Response[0]["custom_fields"];

    //поиск списка телефонов
    for ($i = 0; $i < count($Response); $i++) {
        if ( $Response[$i]["code"] == 'PHONE' ) {
            break;
        }
    }

//    echo 'Количество телефонов ' . count($Response[$i]['values']) . "\n";

    $phones = [];

    # сделать массив из телефонов
    for ($j = 0; $j < count($Response[$i]['values']); $j++) {
        array_push($phones,$Response[$i]['values'][$j]['value']);
    }

    return $phones;
}

/*require("../tokens/access_token.php");

$b = getPhones('2782213', $access_token);
$a = var_export($b,true);

echo $a;
echo "\n";*/

/*$file = '/var/www/html/ovod-krv.ru/amo/print_contact.json';

$b = getPhones($argv[1]);
$a = var_export($b,true);

echo $a;

file_put_contents($file, $a);*/