<?php

namespace Egrul;

use Exception;

/**
 * Класс для скачивания документов с сайта EGRUL.NALOG.RU
 *
 * Class DocumentDownloader
 */
class DocumentDownloader
{
    use DocumentRequests;

    /**
     * Документ, полученный в результате запроса.
     * Формат полученного документа (.pdf)
     *
     * @var
     */
    private $doc;

    /**
     * T-токен ответа от https://egrul.nalog.ru/
     *
     * @var
     */
    private $t;

    /**
     * Данные об организации - ИНН/ОГРН или (ОГРНИП)
     *
     * @var
     */
    private $credentials;

    /**
     * Куки, полученные при обращении к egrul
     *
     * @var
     */
    private $cookie;

    /**
     * Присутствует ли капча на сайте
     *
     * @var
     */
    private $captcha;

    /**
     * Регулярные выражения для проверки входщих данных
     *
     * @var string[][]
     */
    private $regexCredentials = [
        ['/^(?:\d{10}|\d{12})$/i', 'ИНН'],
        ['/^(?:\d{13}|\d{15})$/', 'ОГРН/ОГРНИП'],
    ];

    /**
     * DocumentDownloader constructor.
     *
     * @param $credentials - ИНН/ОГРН(ОГРНИП)
     * @throws Exception
     */
    public function __construct($credentials)
    {
        $this->credentials = $credentials;
        $this->validate();
    }

    /**
     * Проверяет входящие данные на корректность
     * Если параметр не прошел ни одну проверку, выбрасывает ошибку
     *
     * @throws Exception
     * @return void
     */
    private function validate()
    {
        $count = 0;
        foreach ($this->regexCredentials as $key => $value) {
            if (!preg_match($value[0], $this->credentials)) {
                $count++;
            }
        }

        if($count === count($this->regexCredentials)) {
            throw new Exception('Не корректно передан входной параметр.');
        }
    }

    /**
     * Получает куки из заголовков и сохраняет в приватную переменную $cookie
     *
     * @param $headers - заголовки ответа
     * @throws Exception
     * @return void
     */
    private function parseCookieInHeaders($headers)
    {
        $pattern = '/Set-Cookie: (.*)\n/';
        if (preg_match($pattern, $headers, $result)) {
            $this->cookie = explode('; ', $result[1])[0];
        } else {
            throw new Exception('Не удалось извлечь куки файлы из запроса');
        }
    }

    /**
     * GET - параметры для осуществления запроса
     *
     * @return string
     */
    private function getParams()
    {
        $params = array(
            'r' => round(microtime(true) * 1000),
            '_' => round(microtime(true) * 1000)
        );

        return http_build_query($params);
    }

    /**
     * Функция - агрегатор всех запросов
     *
     * @return void
     */
    private function start()
    {
        $this->requestSavedCookie();

        $this->prepareDocumentRequest();

        $this->searchDocumentRequest();

        $this->vypDocumentRequest();

        if($this->statusLoadDocumentRequest() === 'ready') {
            $this->downloadDocumentRequest();
        }
    }

    /**
     * Возвращает булево значение, готов ли документ
     *
     * @return bool
     * @throws Exception
     */
    public function ready()
    {
        $this->start();

        return isset($this->doc);
    }

    /**
     * Возвращает загруженный документ
     *
     * @return mixed
     */
    public function getDocument()
    {
        return $this->doc;
    }
}