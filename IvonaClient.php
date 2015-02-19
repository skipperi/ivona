<?php
namespace backend\helpers;

use Yii;

/**
 * Created by PhpStorm.
 * User: German Igortcev
 * Date: 11.12.14
 * Time: 0:08
 */
class IvonaClient
{
    const   IVONA_ACCESS_KEY = "************";
    const   IVONA_SECRET_KEY = "*****************************";

    public $string;

    public $longDate;
    public $shortDate;

    public $inputType = 'text%2Fplain';
    public $outputFormatCodec = 'MP3';
    public $outputFormatSampleRate = '22050';
    public $parametersRate = 'slow';
    public $voiceLanguage = 'en-US';
    public $voiceName = 'Joey';
    public $xAmzAlgorithm = 'AWS4-HMAC-SHA256';
    public $xAmzSignedHeaders = 'host';
    public $xAmzDate;
    public $xAmzCredential;


    public function __construct()
    {
        $this->setDate();
        $this->setCredential();

    }


    public function get($string)
    {
        $this->string = rawurlencode($string);

        $urlParamsCanonical = '';

        $params = [
            'Input.Data' => $this->string,
            'Input.Type' => $this->inputType,
            'OutputFormat.Codec' => $this->outputFormatCodec,
            'OutputFormat.SampleRate' => $this->outputFormatSampleRate,
            'Parameters.Rate' => $this->parametersRate,
            'Voice.Language' => $this->voiceLanguage,
            'Voice.Name' => $this->voiceName,
            'X-Amz-Algorithm' => $this->xAmzAlgorithm,
            'X-Amz-Credential' => $this->xAmzCredential,
            'X-Amz-Date' => $this->xAmzDate,
            'X-Amz-SignedHeaders' => $this->xAmzSignedHeaders,
        ];

        foreach ($params as $param => $value) {
            $urlParamsCanonical .= $param . '=' . $value . '&';
        }
        $urlParamsCanonical = rtrim($urlParamsCanonical, '&');

        $canonicalizedGetRequest = $this->getCanonicalRequest($urlParamsCanonical);
        $stringToSign = $this->getStringToSign($canonicalizedGetRequest);

        $signature = $this->getSignature($stringToSign);

        $requestUrl = "https://tts.eu-west-1.ivonacloud.com/CreateSpeech?$urlParamsCanonical" .
            "&X-Amz-Signature=$signature";


        try {
            $resource = fopen($requestUrl, 'r');
            return $resource;

        } catch (\ErrorException $e) {
            $mailer = Yii::$app->getMailer();
            $mailer->compose()
                ->setFrom('g.igortcev@gmail.com')
                ->setTo('german.igortcev@gmail.com')
                ->setSubject('Не удалось получить произношение для строки')
                ->setTextBody('Не удалось получить произношение для строки ' . PHP_EOL . $e->getMessage())
                ->send();
        }


    }

    public function setDate()
    {
        $this->longDate = gmdate('Ymd\THis\Z', time());
        $this->shortDate = substr($this->longDate, 0, 8);
        $this->xAmzDate = $this->longDate;
    }

    public function setCredential()
    {
        $this->xAmzCredential = self::IVONA_ACCESS_KEY . "%2F" . $this->shortDate . "%2Feu-west-1%2Ftts%2Faws4_request";
    }


    function getCanonicalRequest($urlParamsCanonical)
    {
        $canonicalizedGetRequest =
            "GET" .
            "\n/CreateSpeech" .
            "\n$urlParamsCanonical" .
            "\nhost:tts.eu-west-1.ivonacloud.com" .
            "\n" .
            "\nhost" .
            "\n" . hash("sha256", "");

        return $canonicalizedGetRequest;
    }

    function getStringToSign($canonicalizedGetRequest)
    {
        $stringToSign = "AWS4-HMAC-SHA256" .
            "\n$this->longDate" .
            "\n$this->shortDate/eu-west-1/tts/aws4_request" .
            "\n" . hash("sha256", $canonicalizedGetRequest);

        return $stringToSign;
    }

    function getSignature($stringToSign)
    {
        $dateKey = hash_hmac('sha256', $this->shortDate, "AWS4" . self::IVONA_SECRET_KEY, true);
        $dateRegionKey = hash_hmac('sha256', "eu-west-1", $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', "tts", $dateRegionKey, true);
        $signingKey = hash_hmac('sha256', "aws4_request", $dateRegionServiceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return $signature;
    }

    function save($resource, $string)
    {
        $fileName = hash('md5', $string) . '.mp3';
        $savePath = Yii::getAlias('@frontend') . '/web/mp3/' . $fileName{0} . '/' . $fileName{1} . '/' . $fileName{2};
        $dbPath = '/mp3/' . $fileName{0} . '/' . $fileName{1} . '/' . $fileName{2} . '/' . $fileName;


        if (!is_dir($savePath)) {
            // dir doesn't exist, make it
            mkdir($savePath, 0755, true);
        }

        file_put_contents($savePath . '/' . $fileName, $resource);

        return $dbPath;
    }


}
