<?php

namespace Aszone\Vulnerabilities;

use Respect\Validation\Validator as v;
use Aszone\FakeHeaders\FakeHeaders;
use GuzzleHttp\Client;

class SqlInjection extends Vulnerability
{
    public function __construct($commandData, $targets)
    {
        //Check command of entered.
        $defaultEnterData = $this->defaultEnterData();
        $this->commandData = array_merge($defaultEnterData, $commandData);
        if ($this->commandData['torl']) {
            $this->commandData['tor'] = $this->commandData['torl'];
        }
        $this->targets = $targets;
    }

    protected function checkSuccess()
    {
        $isValidSqli = $this->isSqlInjection();
        if (!$isValidSqli) {
            return false;
        }

        return $this->setVull();
    }

    protected function isSqlInjection()
    {
        $explodeUrl = parse_url($this->target);
        if (isset($explodeUrl['query'])) {
            return true;
        }

        return false;
    }

    protected function getWordListInArray($wordlist)
    {
        $checkFileWordList = v::file()->notEmpty()->validate($wordlist);
        if ($checkFileWordList) {
            $targetResult = file($wordlist, FILE_IGNORE_NEW_LINES);

            return $targetResult;
        }

        return false;
    }

    protected function setVull()
    {
        $urls = $this->generateUrlByExploit();
        foreach($urls as $url){
            echo "\n url =>".$url."\n";
            $resultcheckAttack = $this->setAttack($url);
            if (!empty($resultcheckAttack)) {
                echo 'Is Vull';

                return $url;
            }
        }

        return false;
    }

    protected function generateUrlByExploit()
    {
        $exploit="'";
        $explodeUrl = parse_url($this->target);
        $explodeQuery = explode('&', $explodeUrl['query']);
        //Identify and sets urls of values of Get
        foreach ($explodeQuery as $keyQuery => $query) {
            $explodeQueryEqual = explode('=', $query);
            $wordsValue[$explodeQueryEqual[0]] = $explodeQueryEqual[1];
        }
        foreach($wordsValue as $keyValue => $value){
            $urls[]=str_replace($keyValue."=".$value,$keyValue."=".$value.$exploit,$this->target);
        }

        return $urls;
    }

    protected function setAttack($url)
    {
        $header = new FakeHeaders();
        $client = new Client(['defaults' => [
            'headers' => ['User-Agent' => $header->getUserAgent()],
            'proxy' => $this->commandData['tor'],
            'timeout' => 30,
        ],
        ]);
        try {
            $body = $client->get($url)->getBody()->getContents();
            if ($body) {
                if ($this->checkErrorSql($body)) {
                    return $url;
                }
            }
        } catch (\Exception $e) {
            if ($e->getCode() != '404' and $e->getCode() != '403') {
                return $url;
            }

            echo 'Error code => '.$e->getCode()."\n";
        }

        return false;
    }

    protected function checkErrorSql($body)
    {
        //echo $body;
        $errors = $this->getErrorsOfList();

        foreach ($errors as $error) {
            $isValid = strpos($body, $error);
            if ($isValid !== false) {
                return true;
            }
        }

        return false;
    }
}
