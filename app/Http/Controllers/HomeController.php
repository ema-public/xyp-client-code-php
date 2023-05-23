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
use Carbon\Carbon;

class HomeController extends Controller
{
    /**
     * Тоон гарын үсгээр мэдээлэл баталгаажуулах сервис дуудах
     * @param Request $request['serialNumber','signature', 'time']
     *
     * @author buyandelger
     * @since 2023-05-23
     */
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

    /**
     * OTP код илгээж буй сервис
     * @param Request $request
     *
     * @author buyandelger
     * @since 2023-05-23
     */
    public function otpApprove(Request $request)
    {
        $accessToken = Config::get('app.xypToken');
        $keyPath = Config::get('app.xypKey');
        $regnum = Config::get('app.regnum');
        $timestamp = Carbon::now()->timestamp;
        $signer = new XypSign($keyPath, $accessToken, $timestamp);
        $signedData = $signer->sign();

        try {
            $wsdl = "https://xyp.gov.mn/meta-1.5.0/ws?WSDL";
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

            $services = array(
                array(
                  'ws' => 'WS100101_getCitizenIDCardInfo'
                )
              );

            $soapParam = [
                "regnum" => $regnum,
                "jsonWSList" => json_encode($services),
                "isSms" => 1, "isApp"=> 0, "isEmail"=> 0, "isKiosk"=> 0, "phoneNum"=> 0
            ];

            $result = $client->WS100008_registerOTPRequest(['request' => $soapParam]);

            if($result->return->resultCode == 0)
                return response()->json(['success' => 0, 'signedData' => $signedData, 'timestamp' => $timestamp], 200);
            else
                return response()->json(['success' => $result->return->resultCode, 'message' => $result->return->resultMessage], 400);

        }
        catch (\Exception $ex) {
            $result = "ХУР -тай холбогдох үед гарсан алдаа:" . $ex->getMessage();
            dd($result);
        }
    }

    /**
     * OTP кодоор мэдээлэл баталгаажуулах сервис
     * @param Request $request
     *
     * @author buyandelger
     * @since 2023-05-23
     */
    public function xypClientOTP(Request $request)
    {
        if($request['otp'] == null)
            return response()->json(['error' => 'otp хоосон байна!'], 400);
        else if($request['otpSignature'] == null)
            return response()->json(['error' => 'signature хоосон байна!'], 400);
        else if($request['otpTimestamp'] == null)
            return response()->json(['error' => 'timestamp хоосон байна!'], 400);

        try {
            $wsdl = "https://xyp.gov.mn/citizen-1.5.0/ws?WSDL";
            $regnum = Config::get('app.regnum');
            $accessToken = Config::get('app.xypToken');
            $signature = $request['otpSignature'];
            $timestamp = $request['otpTimestamp'];
            $otp = $request['otp'];
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
                        "fingerprint" => "",
                        "otp"=>$otp
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
