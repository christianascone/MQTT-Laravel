<?php
/**
 * Created by PhpStorm.
 * User: salman
 * Date: 2/22/19
 * Time: 1:16 PM
 */

namespace Salman\Mqtt\MqttClass;

/*
    Licence
    Copyright (c) 2019 Salman Zafar
    salmanzafar949@gmail.com
    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:
    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.

*/


class Mqtt
{
    protected $client;
    protected $host = null;
    protected $username = null;
    protected $cert_file = null;
    protected $password = null;
    protected $port = null;
    protected $debug = null;
    protected $qos = 0;
    protected $retain = 0;
    protected $publish_ack_timeout_seconds = 5;

    public function __construct()
    {
        $this->host                             = config('mqtt.host');
        $this->username                         = config('mqtt.username');
        $this->password                         = config('mqtt.password');
        $this->cert_file                        = config('mqtt.certfile');
        $this->port                             = config('mqtt.port');
        $this->debug                            = config('mqtt.debug');
        $this->qos                              = config('mqtt.qos');
        $this->retain                           = config('mqtt.retain');
        $this->publish_ack_timeout_seconds      = config('mqtt.publish_ack_timeout_seconds');
    }


    public function ConnectAndPublish($topic, $msg, $client_id=null)
    {
        $id = empty($client_id) ?  rand(0,999) : $client_id;

        $client = new MqttService($this->host,$this->port, $id, $this->cert_file, $this->qos, $this->publish_ack_timeout_seconds, $this->debug);

        if ($client->connect(true, null, $this->username, $this->password))
        {
            $client->publish($topic,$msg, $this->qos, $this->retain);
            $client->close();

            return true;
        }

        return false;

    }

    public function ConnectAndSubscribe($topic, $proc, $client_id=null)
    {
        $id = empty($client_id) ?  rand(0,999) : $client_id;

        $client = new MqttService($this->host,$this->port,$id, $this->cert_file, $this->qos, $this->publish_ack_timeout_seconds, $this->debug);

        if ($client->connect(true, null, $this->username, $this->password))
        {
            $topics[$topic] = array("qos" => 0, "function" => $proc);

            $client->subscribe($topics, $this->qos);

            while($client->proc())
            {

            }

            $client->close();
        }

        return false;
    }

    public function ConnectAndPublishWithAck($topic, $msg, $success_callback, $client_id=null, $publish_ack_timeout_seconds = null)
    {
        $id = empty($client_id) ?  rand(0,999) : $client_id;

        $client = new MqttService($this->host,$this->port,$id, $this->cert_file, $this->qos, $this->publish_ack_timeout_seconds, $this->debug);
        $publish_ack_timeout_seconds = $publish_ack_timeout_seconds != null ? $publish_ack_timeout_seconds : $this->publish_ack_timeout_seconds;

        if ($client->connect(true, null, $this->username, $this->password))
        {
            $is_success = false; // Value to check if ack is ok
            $mutex = false; // Semaphore used to avoid the execution ends before success callback is verified
            $topics[$topic] = array("qos" => 0, "function" => function($topic, $msg) use(&$is_success, &$mutex, $success_callback) {
                $mutex = true;
                $result = call_user_func($success_callback, $topic, $msg);
                if($result === true) $is_success = true;
                $mutex = false;
            });

            $res = $client->subscribe($topics, $this->qos);

            $res = $client->publish($topic,$msg, $this->qos, $this->retain);
            $starttime = microtime(true);
            $run = true;
            while($client->proc() && !$is_success && ($run || $mutex))
            {
                $endtime = microtime(true);
                $timediff = $endtime - $starttime;
                $passed = Helper::secondsToTime($timediff);
                if($passed > $publish_ack_timeout_seconds) $run = false; // Stop running when timeout expires
            }

            $client->close();
        }

        return $is_success;
    }

}
