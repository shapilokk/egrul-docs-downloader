<?php

namespace Egrul;

use Exception;

trait DocumentRequests
{
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
            throw new Exception('Пустой url запроса.');
        }

        $request_headers = array(
            'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Mobile Safari/'
        );


        $ch = curl_init($url);
        if(!$ch) {
            throw new Exception('Не подключен модуль { CURL }.');
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
            throw new Exception('Не удалось осуществить запрос на: ' . $url . '.');
        }

        curl_close($ch);

        return $result;
    }

    /**
     * Первичный запрос на сайт
     * Сохраняет полученные куки
     *
     * @throws Exception
     */
    private function requestSavedCookie()
    {
        $request = $this->curlReq("https://egrul.nalog.ru/index.html", 'GET', array(), true);
        $this->parseCookieInHeaders($request);
    }

    private function vypDocumentRequest()
    {
        $req = $this->curlReq('https://egrul.nalog.ru/vyp-request/' . $this->t . '?' . $this->getParams(), 'GET', array(), false);
        $decode = json_decode($req, 1);
        if ($decode['captchaRequired']) {
            throw new Exception('Сервис временно недоступен.');
        }

        $this->t = $decode['t'];
    }

    /**
     *
     *
     * @throws Exception
     */
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
            throw new Exception('Сервис временно недоступен для скачивания документов.');
        }

        $this->t = $decode['t'];
    }

    /**
     * Проверяет статус готовности сервера
     * Для скачивания документа
     *
     * @return mixed
     * @throws Exception
     */
    private function statusLoadDocumentRequest()
    {
        $url = 'https://egrul.nalog.ru/vyp-status/' . $this->t . '?' . $this->getParams();
        $request = $this->curlReq($url, 'GET', array(), false);
        $parse = json_decode($request, 1);
        return $parse['status'];
    }

    /**
     * Выполняет запрос на скачивание документа
     *
     * @throws Exception
     */
    private function downloadDocumentRequest()
    {
        $this->doc = $this->curlReq('https://egrul.nalog.ru/vyp-download/' . $this->t, 'GET', array(), false);
    }

    /**
     * Получает t - token для скачивания документа
     *
     * @throws Exception
     */
    private function searchDocumentRequest()
    {

        $response = $this->curlReq(
            'https://egrul.nalog.ru/search-result/' . $this->t . '?' . $this->getParams() ,
            'GET',
            array(),
            false
        );

        $parse = json_decode($response, 1);
        $this->t = $parse['rows'][0]['t'];
    }
}