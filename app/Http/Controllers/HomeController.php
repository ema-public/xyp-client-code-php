<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\XypSign\XypSign;
use SoapClient;
use Config;
use Illuminate\Support\Facades\Storage;
use RicorocksDigitalAgency\Soap\Facades\Soap;
use datetime;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\View;

class HomeController extends Controller
{
    public function xypClientSignature(Request $request)
    {
        if($request['serialNumber'] == null)
            return response()->json(['error' => 'serialNumber хоосон байна!'], 400);
        else if($request['signature'] == null)
            return response()->json(['error' => 'signature хоосон байна!'], 400);
        else if($request['time'] == null)
            return response()->json(['error' => 'timestamp хоосон байна!'], 400);

        $accessToken = Config::get('app.xypToken');
        $keyPath = Config::get('app.xypKey');
        $regnum = Config::get('app.regnum');
        $timestamp = $request['time'];

        $signer = new XypSign($keyPath, $accessToken, $timestamp);
        $signedData = $signer->sign();

        try {
            $wsdl = "https://xyp.gov.mn/citizen-1.5.0/ws?WSDL";
            $accessToken = $signedData['accessToken'];
            $signature = $signedData['signature'];
            try
            {
                $client = new SoapClient($wsdl,
                [
                    'soapVersion' => SOAP_1_2,
                    'stream_context' => stream_context_create([
                        'ssl' => [
                            'verify_peer' => false,
                            'allow_self_signed' => true
                        ],
                        'http' => [
                            'header' => "accessToken: $accessToken\r\n".
                            "timeStamp: $timestamp\r\n".
                            "signature: $signature"
                        ]
                    ])
                ]);
            }
            catch(\SoapFault $e)
            {
                dd('Soap error: '.$e);
            }

            $soapParam = [
                "auth" => [
                    "citizen" => [
                        "civilId" => "",
                        "regnum" => $regnum,
                        "certFingerprint" => $request['serialNumber'],
                        "fingerprint" => "",
                        "signature" => $request['signature'],
                        "otp"=>""
                    ],
                    "operator" => [
                        "fingerprint" => "",
                        "regnum" => "",
                        "otp"=>""
                    ]
                ],
                "regnum" => $regnum,
            ];

            $result = $client->WS100101_getCitizenIDCardInfo(['request' => $soapParam]);
            dd($result->return->response);
            // return $result->return->response;
        }
        catch (\Exception $ex) {
            $result = "ХУР -тай холбогдох үед гарсан алдаа:" . $ex->getMessage();
            dd($result);
            // return $result;
        }
    }


}
