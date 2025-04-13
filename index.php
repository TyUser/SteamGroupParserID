<?php
// SPDX-License-Identifier: GPL-3.0-only
/**
 * Парсер группы Steam для извлечения идентификаторов групп
 */
class SteamGroupParser
{
    /**
     * Базовый URL для страниц групп Steam
     */
    private const STEAM_GROUP_BASE_URL = 'https://steamcommunity.com/groups/';

    /**
     * Минимальная длина XML контента для проверки валидности
     */
    private const MIN_XML_LENGTH = 100;

    /**
     * Минимальная длина идентификатора группы
     */
    private const GROUP_ID_MIN_LENGTH = 10;

    /**
     * Константа смещения для расчета финального ID
     */
    private const OFFSET_VALUE = 1429521408;

    /**
     * Максимальная длина URL
     */
    private const MAX_POST_LENGTH = 70;

    /**
     * Длительность кэширования в секундах (1 час)
     */
    private const CACHE_DURATION = 3600;

    /**
     * Хранит сообщение об ошибке последней операции
     *
     * @var string
     */
    private string $errorMessage = '';


    private string $urlSteamGroup = '';

    /**
     * Извлекает числовой идентификатор группы из URL
     *
     * @param string $id идентификатор для _POST запроса
     * @return int Идентификатор группы или 0 в случае ошибки
     * @throws Exception
     */
    public function parseGroupId(string $id): int
    {
        $url = '';
        if (isset($_POST[$id])) {
            $url = substr($_POST[$id], 0, self::MAX_POST_LENGTH);
        }

        if ($url === '') {
            $this->errorMessage = 'Узнать ID группы Steam по адресу URL. Необходим для sv_steamgroup';
            return 0;
        }

        // Проверка соответствия URL базовой схеме Steam
        if (similar_text($url, self::STEAM_GROUP_BASE_URL) !== 34) {
            $this->errorMessage = 'Неверный формат URL группы Steam';
            return 0;
        }

        $this->urlSteamGroup = $url;

        // Проверяем кэш
        $cachedResult = $this->getCachedResult($url);
        if ($cachedResult > 0) {
            return $cachedResult;
        }

        // Получаем XML данные
        $xmlContent = file_get_contents($url . '/memberslistxml?xml=1');

        // Проверяем валидность XML данных
        if (strlen($xmlContent) < self::MIN_XML_LENGTH) {
            $this->errorMessage = 'Слишком короткий XML ответ';
            return 0;
        }

        // Обрабатываем XML и получаем ID группы
        $xml = new SimpleXMLElement($xmlContent);
        $groupId64 = (string)$xml->groupID64;

        unset($xmlContent, $xml);

        // Проверяет валидность XML содержимого
        if (strlen($groupId64) < self::GROUP_ID_MIN_LENGTH) {
            $this->errorMessage = 'Неверный формат ID группы';
            return 0;
        }

        // Преобразует 64-битный ID в окончательное значение
        $i = 0;
        $result = '';
        $digits = str_split($groupId64);

        foreach ($digits as $a) {
            $i += 1;
            if ($i > 8) {
                $result .= $a;
            }
        }

        $result = intval($result);

        if ($result > self::OFFSET_VALUE) {
            $finalResult = $result - self::OFFSET_VALUE;

            // Кэшируем результат
            $this->cacheResult($url, $finalResult);
            return $finalResult;
        }

        $this->errorMessage = 'Логическая ошибка parseGroupId';
        return 0;
    }

    /**
     * Получает кэшированное значение для URL
     *
     * @param string $key Ключ кэша (MD5 хеш URL)
     * @return int кэшированное значение или 0 если не найдено
     */
    private function getCachedResult(string $key): int
    {
        if ($this->hx_get_cache('temp/' . md5($key))) {
            $h1 = fopen('temp/' . md5($key), "r");
            if ($h1) {
                $sg_content1 = trim(fgets($h1, 50));
                fclose($h1);

                return $sg_content1;
            }
        }

        return 0;
    }

    /**
     * Проверяет актуальность кэша
     *
     * @param string $filename Имя файла кэша
     * @return bool True если кэш актуален
     */
    private function hx_get_cache(string $filename): bool
    {
        if (file_exists($filename)) {
            $i2 = filemtime($filename);
            if ((time() - self::CACHE_DURATION) < $i2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Сохраняет результат в кэш. Логируем запрос.
     *
     * @param string $key URL группы
     * @param int $result Сохраняемое значение
     */
    private function cacheResult(string $key, int $result): void
    {
        $handle = fopen('temp/' . md5($key), 'w');
        if ($handle) {
            fwrite($handle, $result . "\n");
            fclose($handle);
        }

        $handle2 = fopen('temp/log.txt', 'a');
        if ($handle2) {
            fwrite($handle2, $key . ' [' . $result . "]\n");
            fclose($handle2);
        }
    }

    /**
     * Возвращает сообщение об последней ошибке
     *
     * @return string Текст ошибки
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Возвращает Url стим группы
     *
     * @return string Url
     */
    public function getUrlSteamGroup(): string
    {
        return $this->urlSteamGroup;
    }
}

$parser = new SteamGroupParser();

$id = $parser->parseGroupId('id');
$error = $parser->getErrorMessage();
$url = $parser->getUrlSteamGroup();

$message = '';
$message2 = '';

if ($id > 0) {
    $message = $url;
    $message2 = $url . '<br>sv_steamgroup = "' . $id . '"';
} else {
    $message = 'Образец https://steamcommunity.com/groups/l4d_club';
    $message2 = $error;
}

echo '
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Идентификатор группы в стиме (Steam groupID)">
    <meta name="keywords" content="Steam groupID">
    <link rel="stylesheet" type="text/css" href="system/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="system/style.css">
    <title>Узнать номер стим группы</title>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark text-uppercase">
    <div class="container">
        <a class="navbar-brand" href="https://www.example.com/">
            <span class="text-white">www.example.com</span>
        </a>
    </div>
</nav>

    <div style="padding-top: 60px;"></div>

    <div class="container">

        <form action="index.php" method="post" class="d-flex justify-content-center gap-2 mb-3">
            <div class="col-auto">
                <input type="search" name="id" placeholder="' . $message . '" maxlength="70" class="form-control" style="min-width: 550px;">
            </div>
            <button type="submit" class="btn btn-primary">Отправить</button>
        </form>
        <p class="text-center">' . $message2 . '</p>
    </div>
</body>
</html>';
