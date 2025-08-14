<?php

namespace App\CentralLogics;

use Illuminate\Support\Facades\DB;
use Twilio\Rest\Client;

class SMS_module
{
    public static function send($receiver, $otp)
    {
        $config = self::get_settings('twilio');
        if (isset($config) && $config['status'] == 1) {
            return self::twilio($receiver, $otp);
        }
        
        $config = self::get_settings('nexmo');
        if (isset($config) && $config['status'] == 1) {
            return self::nexmo($receiver, $otp);
        }
        
        $config = self::get_settings('2factor');
        
        if (isset($config) && $config['status'] == 1) {
            return self::two_factor($receiver, $otp);
        }
        
        $config = self::get_settings('msg91');
        if (isset($config) && $config['status'] == 1) {
            return self::msg_91($receiver, $otp);
        }
        $config = self::get_settings('alphanet_sms');
        if (isset($config) && $config['status'] == 1) {
            return self::alphanet_sms($receiver, $otp);
        }
        
        return 'not_found';
    }
    
    public static function twilio($receiver, $otp): string
    {
        $config = self::get_settings('twilio');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            $message = str_replace("#OTP#", $otp, $config['otp_template']);
            $sid = $config['sid'];
            $token = $config['token'];
            try {
                $twilio = new Client($sid, $token);
                $twilio->messages
                ->create($receiver, // to
                array(
                    "messagingServiceSid" => $config['messaging_service_sid'],
                    "body" => $message
                    )
                );
                $response = 'success';
            } catch (\Exception $exception) {
                $response = 'error';
            }
        }
        return $response;
    }
    
    public static function nexmo($receiver, $otp): string
    {
        $config = self::get_settings('nexmo');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            $message = str_replace("#OTP#", $otp, $config['otp_template']);
            try {
                $ch = curl_init();
                
                curl_setopt($ch, CURLOPT_URL, 'https://rest.nexmo.com/sms/json');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "from=".$config['from']."&text=".$message."&to=".$receiver."&api_key=".$config['api_key']."&api_secret=".$config['api_secret']);
                
                $headers = array();
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                
                $result = curl_exec($ch);
                if (curl_errno($ch)) {
                    echo 'Error:' . curl_error($ch);
                }
                curl_close($ch);
                $response = 'success';
            } catch (\Exception $exception) {
                $response = 'error';
            }
        }
        return $response;
    }
    public static function two_factor($receiver, $otp): string
    {
        $config = self::get_settings('2factor');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            
            $api_key = $config['api_key'];
            $otp_template = $config['otp_template'];
            if(empty($otp_template)){
                $otp_template ="OTP3";
            }
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://2factor.in/API/V1/" . $api_key . "/SMS/" . $receiver . "/" . $otp . "/".$otp_template,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            if (!$err) {
                $response = 'success';
            } else {
                $response = 'error';
            }
        }
        return $response;
    }
    public static function send_trasaction_sms($receiver, $message = null, $variables = []): string
    {
        $config = self::get_settings('2factor');
        if (!isset($config) || $config['status'] != 1) {
            return 'error';
        }
        
        $api_key = $config['api_key'];
        $receiver=str_replace("+","",$receiver);
        //   return $receiver = preg_replace('/\D/', '', $receiver); // keep only digits
    
        // If template_id is provided, send via template
        if (!empty($variables)) {
            
            $order_id=$variables[0];
            $items_list=$variables[1];
                $message=str_replace("#Var1#",$order_id,$message);
                   $message=str_replace("#Var2#",$items_list,$message);
            $senderId = "ozi"; // your sender ID
            
            $ch = curl_init();
            
            curl_setopt_array($ch, array(
                CURLOPT_URL => 'https://2factor.in/API/R1/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query([
                    'module' => 'TRANS_SMS',
                    'apikey' => $api_key,
                    'to'     => $receiver,       // comma-separated for bulk
                    'from'   => $senderId,
                    'msg'    => $message
                ]),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded'
                ),
            ));
            $response = curl_exec($ch);
            curl_close($ch);
            
            return $response;
            
        } 
        // Otherwise send plain message
        else {
            $payload = json_encode([
                "From" => "ozi", // Sender ID
                "To" => $receiver,
                "Msg" => $message
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://2factor.in/API/V1/{$api_key}/ADDON_SERVICES/SEND/TSMS",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }
    }
    
    //  public static function two_factor($receiver, $otp): string
    // {
    //     $config = [
    //         'status' => 1,
    //         'userid' => '2000231339',
    //         'password' => 'Hs!*N!cn',
    //     ];
    //         $aconfig = self::get_settings('2factor');
    
    
    //     if (isset($config) && $config['status'] == 1) {
    
    //             // $message = str_replace("#OTP#", $otp, $aconfig['otp_template']);
    //         $userid = $config['userid'];
    //         $password = $config['password']; // No urlencode here
    //         $message = "We have received your order request for Quotation ID : $otp. Our team will be in touch with you shortly. Thank you !\nZoplar";
    
    //         $url = "https://enterprise.smsgupshup.com/GatewayAPI/rest?" . http_build_query([
    //             'method'       => 'SendMessage',
    //              'send_to'      => '91'.substr($receiver, -10),
    //             'msg'          => $message, // No manual urlencode
    //             'msg_type'     => 'TEXT',
    //             'userid'       => $userid,
    //             'auth_scheme'  => 'plain',
    //             'password'     => $password,
    //             'v'            => '1.1',
    //             'format'       => 'text',
    //         ]);
    
    //         $curl = curl_init();
    //         curl_setopt_array($curl, [
    //             CURLOPT_URL => $url,
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_TIMEOUT => 30,
    //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //             CURLOPT_CUSTOMREQUEST => "GET",
    //         ]);
    
    //         $result = curl_exec($curl);
    //         $err = curl_error($curl);
    //         curl_close($curl);
    
    
    //         if (!$err && stripos($result, 'success') !== false) {
    //             $response = 'success';
    //         } else {
    //             $response = 'error';
    //         }
    //     }
    
    //     return $response;
    // }
    
    
    public static function msg_91($receiver, $otp): string
    {
        $config = self::get_settings('msg91');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            $receiver = str_replace("+", "", $receiver);
            $curl = curl_init();
            // $message = str_replace("#OTP#", $otp, $config['otp_template']);
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.msg91.com/api/v5/otp?template_id=" . $config['template_id'] . "&mobile=" . $receiver . "&authkey=" . $config['auth_key'] . "",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_POSTFIELDS => "{\"OTP\":\"$otp\"}",
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/json"
                ),
            ));
            
            // curl_setopt_array($curl, [
            //     CURLOPT_URL => "https://control.msg91.com/api/v5/flow",
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_ENCODING => "",
            //     CURLOPT_MAXREDIRS => 10,
            //     CURLOPT_TIMEOUT => 30,
            //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //     CURLOPT_CUSTOMREQUEST => "POST",
            //     CURLOPT_POSTFIELDS => json_encode([
            //         "template_id" => $config['template_id'],
            //         "short_url" => "0", // Set to "1" (On) or "0" (Off) as needed
            //         "short_url_expiry" => "3600", // Replace with expiry time in seconds (optional)
            //         "realTimeResponse" => "0",
            //         "recipients" => [
            //             [
            //                 "mobiles" => $receiver,
            //                 "OTP" => $otp,
            //                 // "VAR1" => $var // Replace with your second variable value, if any
            //             ]
            //         ]
            //     ]),
            //     CURLOPT_HTTPHEADER => [
            //         "accept: application/json",
            //         "authkey: " . $config['auth_key'],
            //         "content-type: application/json"
            //     ],
            // ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if (!$err) {
                $response = 'success';
            } else {
                $response = 'error';
            }
        }
        return $response;
    }
    
    public static function alphanet_sms($receiver, $otp ,$message = null): string
    {
        $config = self::get_settings('alphanet_sms');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            if($message ==  null){
                $message = str_replace("#OTP#", $otp, $config['otp_template']);
            }
            
            $receiver = str_replace("+", "", $receiver);
            $api_key = $config['api_key'];
            $sender_id = $config['sender_id'] ?? null;
            
            
            $postfields = array(
                'api_key' => $api_key,
                'msg' => $message,
                'to' => $receiver
            );
            
            if ($sender_id) {
                $postfields['sender_id'] = $sender_id;
            }
            
            
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.sms.net.bd/sendsms',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $postfields,
            ));
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            if ((int) data_get(json_decode($response,true),'error') === 0) {
                $response = 'success';
            } else {
                $response = 'error';
            }
        }
        return $response;
    }
    
    
    
    
    public static function get_settings($name)
    {
        $config = DB::table('addon_settings')->where('key_name', $name)
        ->where('settings_type', 'sms_config')->first();
        
        if (isset($config) && !is_null($config->live_values)) {
            return json_decode($config->live_values, true);
        }
        return null;
    }
}