<?php

/**
 * Класс для скачивания документов с сайта EGRUL.NALOG.RU
 *
 * Class EgrulDocumentDownloader
 */
class EgrulDocumentDownloader
{
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
     * Формирует запрос на сайт
     *
     * @param string $url
     * @param string $method
     * @param array $params
     * @param bool $returnHeaders
     * @return false|resource
     * @throws Exception
     */
    private function curlReq(string $url, string $method = 'POST', array $params = array(), bool $returnHeaders = true)
    {
        if(empty($url)) {
            throw new \Exception('Пустой url запроса.');
        }

        $request_headers = array(
            'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Mobile Safari/'
        );


        $ch = curl_init($url);
        if(!$ch) {
            throw new \Exception('Не подключен модуль CURL для PHP.');
        }

        if(!empty($this->cookie)) {
            $request_headers[] = 'Cookie: ' . $this->cookie;
        }

        curl_setopt($ch, CURLOPT_HEADER, $returnHeaders);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $result = curl_exec($ch);
        if ($result === false) {
            throw new \Exception('Не удалось осуществить запрос на: ' . $url . '.');
        }

        curl_close($ch);

        return $result;
    }


    public function __construct($credentials)
    {
        $this->credentials = $credentials;

        $this->requestSavedCookie();
    }

    /**
     * Получает куки из заголовков и сохраняет в приватную переменную $cookie
     *
     * @param $headers - заголовки ответ
     * @throws Exception - ошибка в случает неудачного парсинга заголовков
     */
    private function parseCookieInHeaders($headers)
    {
        $pattern = '/Set-Cookie: (.*)\n/';
        if (preg_match($pattern, $headers, $result)) {
            $this->cookie = explode('; ', $result[1])[0];
        } else {
            throw new \Exception('Не удалось извлечь куки файлы из запроса');
        }
    }

    private function getParams()
    {
        return array(
            'r' => round(microtime(true) * 1000),
            '_' => round(microtime(true) * 1000)
        );
    }

    /**
     * Получает t - token для скачивания документа
     *
     * @throws Exception
     */
    private function searchDocumentRequest()
    {

        $queryString = http_build_query($this->getParams());

        $response = $this->curlReq('https://egrul.nalog.ru/search-result/' . $this->t . '?' . $queryString , 'GET', array(), false);

        $parse = json_decode($response, 1);

        $this->t = $parse['rows'][0]['t'];
    }

    private function vypDocumentRequest()
    {
        $url = 'https://egrul.nalog.ru/vyp-request/' . $this->t . '?' . http_build_query($this->getParams());

        $req = $this->curlReq($url, 'GET', array(), false);

        $decode = json_decode($req, 1);
        if ($decode['captchaRequired']) {
            throw new \Exception('Сервис временно недоступен.');
        }

        $this->t = $decode['t'];
    }

    private function requestSavedCookie()
    {
        $request = $this->curlReq("https://egrul.nalog.ru/index.html", 'GET', array(), true);
        $this->parseCookieInHeaders($request);
    }

    private function prepareDocumentRequest()
    {
        $postData = array(
            'vyp3CaptchaToken' => '',
            'page' => '',
            'query' => $this->credentials,
            'region' => ''
        );

        $request = $this->curlReq('https://egrul.nalog.ru/', 'POST', $postData, false);

        $decode = json_decode($request, 1);
        if($decode['captchaRequired']) {
            throw new \Exception('Сервис временно недоступен для скачивания документов.');
        }

        $this->t = $decode['t'];
    }

    private function statusLoadDocumentRequest()
    {
        $url = 'https://egrul.nalog.ru/vyp-status/' . $this->t . '?' . http_build_query($this->getParams());

        $request = $this->curlReq($url, 'GET', array(), false);

        $parse = json_decode($request, 1);
        return $parse['status'];
    }

    private function downloadDocumentRequest()
    {
        $url = 'https://egrul.nalog.ru/vyp-download/' . $this->t;
        $this->doc = $this->curlReq($url, 'GET', array(), false);
    }

    private function start()
    {
        $this->prepareDocumentRequest();

        $this->searchDocumentRequest();

        $this->vypDocumentRequest();

        $status = $this->statusLoadDocumentRequest();
        if($status === 'ready') {
            $this->downloadDocumentRequest();
        }
    }

    /**
     * Возвращает булево значение, готов ли документ
     *
     * @return bool
     */
    public function ready()
    {
        $this->start();

        return isset($this->doc);
    }

    public function getDocument()
    {
        return $this->doc;
    }
}

try {
    $doc = new EgrulDocumentDownloader('1037700258694');
    if($doc->ready()) {
        $document = $doc->getDocument(); // Биннарник pdf документа
         file_put_contents('doc.pdf', $document);
    }
} catch (Exception $e) {
    exit($e);
}