<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;600&display=swap" rel="stylesheet">

        <script src="https://cdn.jsdelivr.net/npm/node-forge@1.0.0/dist/forge.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <script src="https://unpkg.com/@peculiar/x509"></script>

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: #636b6f;
                font-family: 'Nunito', sans-serif;
                font-weight: 200;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="flex-center position-ref full-height" id="resHTML">
            <div class="content">
                @csrf
                <div class="title m-b-md">
                    Laravel
                </div>
                <button onclick="otpRequest()" id="otpBtn">Otp Submit</button>
                <button onclick="webSocket()">Эхлүүлэх</button>
                @if($result != null)
                    <p>Ургийн овог: {{$result->surname}}</p>
                    <p>Овог: {{$result->lastname}}</p>
                    <p>Нэр: {{$result->firstname}}</p>
                    <p>Регистр: {{$result->regnum}}</p>
                @else
                    <p></p>
                @endif
            </div>
        </div>
        <form id="form" action="/service" method="POST">
            @csrf
            <input type="hidden" name="serialNumber" id ="serialNumber" value=""/>
            <input type="hidden" name="signature" id ="signature" value=""/>
            <input type="hidden" name="time" id ="time" value=""/>
        </form>
        <form id="otpForm" action="/clientOTP" method="POST">
            @csrf
            <input type="hidden" name="otpSignature" id ="otpSignature" value=""/>
            <input type="hidden" name="otpTimestamp" id ="otpTimestamp" value=""/>
            <input type="hidden" name="otp" id ="otp" value=""/>
        </form>
    </body>
    <script>

        function getMessage(serialNumber, signature, time) {
            console.log('GetMessage: ' + serialNumber + 'XypSign: ' + signature);
            $('#time').val(time);
            $('#serialNumber').val(serialNumber);
            $('#signature').val(signature);
            $('#form').submit();
         }


        function otpRequest()
        {
            $(document).ready(function() {
                $("#otpBtn").click(function() {
                    $.ajax({
                    url: "{{ route('otp') }}",
                    type: "POST",
                    dataType: "json",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        console.log('ResultCode: ' + response.success);
                        if(response.success == 0)
                        {
                            var otp = prompt('OTP кодоо оруулна уу!');
                            if(otp != null)
                            {
                                console.log(response);
                                $('#otpSignature').val(response.signedData.signature);
                                $('#otpTimestamp').val(response.timestamp);
                                $('#otp').val(otp);
                                $('#otpForm').submit();
                            }
                            else
                                console.log('OTP хоосон байна!');
                        }
                        else
                            console.log(response);
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                    });
                });
            });
        }

        function webSocket()
        {
            var socket = new WebSocket('ws://127.0.0.1:59001');
            let time = Math.floor(+new Date() / 1000);
            socket.onopen = function(event) {
                console.log("WebSocket connection established.");
                onOpen(socket, time);
            };

            socket.onmessage = function(event) {
                if(event != undefined || event.data != undefined)
                {
                    console.log('OnMessage: ' + event);
                    const sign = JSON.parse(event.data);
                    let serialNumber = getSerialNumber(sign['certificate']);
                    let signature = sign['signature'];
                    getMessage(serialNumber, signature, time);
                }
            };

            socket.onclose = function(event) {
                console.log("WebSocket connection closed.");
            };

            socket.onerror = function(event) {
                console.error("WebSocket error: " + event);
            };

        }

        function onOpen(socket, time) {
            function run() {
                var regnum = '{{ Config::get('app.regnum') }}';
                console.log("REGNUM: " + regnum);
                var dataSign = regnum + '.' + time;
                var x = JSON.stringify({type: 'e457cb50ed64bde0', data: dataSign});
                setTimeout(() => {
                    socket.send(x);
                    setTimeout(() => {
                        var result = socket.onmessage();
                        socket.close();
                        return result;
                    }, 1000);
                }, 1000);
            }

            run();
        }

        function parseCertificate(certBase64) {
            const certBuffer = base64ToBuffer(certBase64);
            const cert = new x509.X509Certificate(certBuffer);
            return cert;
        }

        function base64ToBuffer(base64) {
            try {
                const binaryString = atob(base64);
                const length = binaryString.length;
                const bytes = new Uint8Array(length);

                for (let i = 0; i < length; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }

                return bytes.buffer;
            } catch (error) {
                console.error('Failed to decode the string:', error);
            }
        }

        function getSerialNumber(certBase64) {
            const cert = parseCertificate(certBase64);
            const serialNumberBuffer = cert.serialNumber;
            return serialNumberBuffer.toString('hex');
        }

    </script>
</html>
