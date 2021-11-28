<?php

namespace Asinius\Network\tests\units;

use atoum;

class Address extends atoum
{

    private $_valid_addresses = [
        '192.168.0.0'               => ['type' => \Asinius\Network\Address::NET_ADDR_IP4, 'string' => '192.168.0.0', 'octets' => [192, 168, 0, 0]],
        '2607:f8b0:400a:805::200e'  => ['type' => \Asinius\Network\Address::NET_ADDR_IP6, 'string' => '2607:f8b0:400a:805::200e', 'octets' => [38, 7, 248, 176, 64, 10, 8, 5, 0, 0, 0, 0, 0, 0, 32, 14]],
        '2607:F8B0:400A:805::200E'  => ['type' => \Asinius\Network\Address::NET_ADDR_IP6, 'string' => '2607:f8b0:400a:805::200e', 'octets' => [38, 7, 248, 176, 64, 10, 8, 5, 0, 0, 0, 0, 0, 0, 32, 14]],
        'AB:54:00:DE:54:04'         => ['type' => \Asinius\Network\Address::NET_ADDR_MAC, 'string' => 'ab:54:00:de:54:04', 'octets' => [171, 84, 0, 222, 84, 4]],
    ];


    public function testValidAddresses ()
    {
        foreach ($this->_valid_addresses as $address => $properties) {
            $object = new \Asinius\Network\Address($address);
            $this->object($object)->isInstanceOf('\Asinius\Network\Address');
            $this->integer($object->type)->isEqualTo($properties['type']);
            $this->string("$object")->isEqualTo($properties['string']);
            $this->array($object->octets)->isEqualTo($properties['octets']);
        }
    }
}